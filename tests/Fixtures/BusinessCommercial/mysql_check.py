#!/usr/bin/env python3
"""Incremental F5 admission races on the approved disposable MySQL server."""
import argparse
import fcntl
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import re
import secrets
import signal
import subprocess
import time
import uuid

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[2]
SPEC = importlib.util.spec_from_file_location("business_mysql_infrastructure", HERE.parent / "BusinessIdentity/mysql_check.py")
INFRA = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(INFRA)
CASES = ("last_slot", "same_operation", "conflicting_operation", "fiscal_alias",
         "commit_cancel", "reserve_cancel", "reference_commit", "shared_commit_cancel",
         "users_last_slot", "users_acquire_release", "users_reactivation_release")


def check(condition, message):
    if not condition:
        raise RuntimeError(message)


def sources():
    paths = set()
    for folder in ("app", "config", "routes", "database/migrations", "tests/Support"):
        paths.update((ROOT / folder).rglob("*.php"))
    paths.update([HERE / "mysql_worker.php", Path(__file__), HERE / "test_mysql_runner.py",
                  ROOT / "tests/Unit/BusinessIdentity/BusinessCommercialRuntimeGuardTest.php",
                  ROOT / "tests/Unit/BusinessIdentity/BusinessIdentityMySqlGuardTest.php", HERE.parent / "BusinessIdentity/mysql_runtime.php",
                  HERE.parent / "BusinessIdentity/mysql_check.py", ROOT / "database/seeders/BusinessCatalogSeeder.php",
                  ROOT / "bootstrap/app.php", ROOT / "bootstrap/providers.php", ROOT / "composer.lock",
                  ROOT.parent / "byslaudaapi/tests/Support/BysRaceDatabase.php"])
    result = {}
    for path in sorted(paths):
        check(not path.is_symlink(), "Unexpected source symlink.")
        result[str(path.relative_to(ROOT.parent))] = hashlib.sha256(path.read_bytes()).hexdigest()
    return result


def write(path, value):
    path.write_text(json.dumps(value, indent=2, ensure_ascii=False) + "\n")
    path.chmod(0o600)


class Verification:
    def __init__(self, base, mysql):
        self.base, self.mysql, self.sequence, self.assertions = base, mysql, 0, 0
        self.env = {**os.environ, "LAUDA_BUSINESS_RUNTIME": str(base), "LAUDA_BUSINESS_MYSQL": "1" if mysql else "0"}
        for key in ("APP_CONFIG_CACHE", "APP_ROUTES_CACHE", "APP_EVENTS_CACHE", "APP_SERVICES_CACHE", "APP_PACKAGES_CACHE"):
            self.env.pop(key, None)

    def expect(self, condition, message):
        self.assertions += 1
        check(condition, message)

    def artifact(self, name):
        self.sequence += 1
        return self.base / f"{self.sequence:03d}-{name}"

    def worker(self, **action):
        output = INFRA.run(["php", str(HERE / "mysql_worker.php")], self.env, json.dumps(action),
                           artifact=self.artifact(action["action"]))
        return INFRA.json_output(output, action["action"])

    def request(self, service, operation, payload=None):
        return {"action": "request", "service": service, "operation": operation, "payload": payload or {}}

    def ok(self, action):
        result = self.worker(**action)
        self.expect(result["status"] == 200, f"Preparation failed: {result}")
        return result["body"]

    def reserve(self, source, fiscal=None, document=None):
        value = {"source_reference": source}
        if fiscal is not None:
            value["fiscal"] = fiscal
        if document is not None:
            value["document_id"] = document
        return {"operation_key": str(uuid.uuid4()), "document": value}

    def race(self, name, actions, reverse):
        order = [1, 0] if reverse else [0, 1]
        if not self.mysql:
            rows = [None, None]
            for index in order:
                rows[index] = self.worker(**actions[index])
            return rows, {"concurrent": False, "lock_waits": 0}
        processes, rows, artifacts = {}, [None, None], {}
        prefix = self.base / name

        def start(index, role):
            command = ["php", str(HERE / "mysql_worker.php")]
            process = subprocess.Popen(command, cwd=ROOT, env=self.env, stdin=subprocess.PIPE,
                                       stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
            INFRA.PROCESSES.add(process)
            processes[index] = process
            artifacts[index] = self.artifact(role)
            process.stdin.write(json.dumps({**actions[index], "barrier": {"name": name, "role": role}}))
            process.stdin.close()
            process.stdin = None
            deadline = time.monotonic() + 10
            marker = Path(f"{prefix}.{role}.ready")
            while not marker.exists():
                check(process.poll() is None, f"Worker exited before {role} barrier.")
                check(time.monotonic() < deadline, f"Timeout at {role} barrier.")
                time.sleep(0.01)
            return json.loads(marker.read_text())

        try:
            holding = start(order[0], "hold")
            waiting = start(order[1], "wait")
            self.expect(holding["driver"] == waiting["driver"] == "mysql", "Both workers must use MySQL.")
            self.expect(holding["connection_id"] != waiting["connection_id"], "Workers reused a connection.")
            self.expect(holding["database"] == waiting["database"], "Workers use different schemas.")
            lock = self.worker(action="locks", holding=holding["connection_id"], waiting=waiting["connection_id"])
            self.expect(lock["lock_waits"] > 0, "MySQL did not confirm contention on the company mutex.")
            self.expect(processes[order[1]].poll() is None, "Second worker completed before mutex release.")
            Path(f"{prefix}.go").touch(mode=0o600)
            for index, process in processes.items():
                stdout, stderr = process.communicate(timeout=25)
                INFRA.record_process(artifacts[index], ["php", str(HERE / "mysql_worker.php")], process, stdout, stderr)
                check(process.returncode == 0, f"Race worker failed: {(stderr or stdout)[-3000:]}")
                rows[index] = INFRA.json_output(stdout, name)
                self.expect(rows[index]["barrier_reached"], "Worker skipped the mutex barrier.")
            return rows, {"concurrent": True, "lock_waits": lock["lock_waits"], "holding": holding, "waiting": waiting}
        finally:
            for index, process in processes.items():
                INFRA.stop(process)
                if not Path(f"{artifacts[index]}.process.json").exists():
                    stdout, stderr = process.communicate()
                    INFRA.record_process(artifacts[index], ["php", str(HERE / "mysql_worker.php")], process, stdout, stderr)

    def users_case(self, case, reverse):
        before = self.assertions
        self.worker(action="reset")
        for service in ("pos", "bys", "ecf", "cumplimiento"):
            key = str(uuid.uuid4())
            self.ok(self.request(service, "capacity:start_inventory", {"owner_user_id": 501, "operation_key": key}))
            self.ok(self.request(service, "capacity:inventory_user", {"local_user_id": 501, "operation_key": key}))
            self.ok(self.request(service, "capacity:finish_inventory", {"operation_key": key, "inventory_count": 1,
                                                                      "inventory_hash": hashlib.sha256(b"[501]").hexdigest()}))
        first = {"local_user_id": 502, "operation_key": str(uuid.uuid4()), "previous_key": None}
        a = self.request("pos", "capacity:acquire_user", first)
        if case == "users_last_slot":
            b = self.request("bys", "capacity:acquire_user", {**first, "operation_key": str(uuid.uuid4())})
        elif case == "users_acquire_release":
            b = self.request("pos", "capacity:release_user", first)
        else:
            self.ok(a)
            a = self.request("pos", "capacity:acquire_user", {**first, "operation_key": str(uuid.uuid4()), "previous_key": first["operation_key"]})
            b = self.request("pos", "capacity:release_user", first)
        rows, proof = self.race(case + ("-reverse" if reverse else "-forward"), [a, b], reverse)
        statuses = [row["status"] for row in rows]
        state = self.ok(self.request("pos", "capacity:inspect"))
        if case == "users_last_slot":
            self.expect(statuses == ([429, 200] if reverse else [200, 429]), "Two users took the final seat.")
            self.expect(state["users_consumed"] == 1, "Seat count mismatch.")
        elif case == "users_acquire_release":
            self.expect(statuses == ([409, 200] if reverse else [200, 200]), "Released acquisition was resurrected.")
            self.expect(state["users_consumed"] == 0, "Release left an occupied seat.")
            self.expect(self.worker(**a)["status"] == 409, "Replay resurrected a released seat.")
        else:
            self.expect(statuses == ([409, 200] if reverse else [200, 200]), "Stale CAS or release was accepted.")
            self.expect(state["users_consumed"] == (0 if reverse else 1), "Old release deleted new activation.")
        return {"case": case, "order": "reverse" if reverse else "forward", "status": "passed",
                "assertions": self.assertions - before, "http_statuses": statuses, "state": state, "proof": proof}

    def case(self, case, reverse):
        if case.startswith("users_"):
            return self.users_case(case, reverse)
        before = self.assertions
        self.worker(action="reset")
        first = self.reserve("qa:first")
        second = self.reserve("qa:second")
        a = self.request("pos", "reserve", first)
        b = self.request("bys", "reserve", second)
        if case in ("same_operation", "conflicting_operation"):
            b = self.request("pos", "reserve", first if case == "same_operation" else {**first, "document": second["document"]})
        elif case in ("fiscal_alias", "shared_commit_cancel"):
            fiscal = {"issuer": "101010101", "ncf": "E310000000001"}
            first = self.reserve("qa:first", fiscal)
            second = self.reserve("qa:second", fiscal)
            a, b = self.request("pos", "reserve", first), self.request("ecf", "reserve", second)
            if case == "shared_commit_cancel":
                self.ok(a)
                self.ok(b)
                a = self.request("pos", "commit", {"operation_key": first["operation_key"]})
                b = self.request("ecf", "cancel", {"operation_key": second["operation_key"]})
        elif case == "commit_cancel":
            self.ok(a)
            a = self.request("pos", "commit", {"operation_key": first["operation_key"]})
            b = self.request("pos", "cancel", {"operation_key": first["operation_key"]})
        elif case == "reserve_cancel":
            b = self.request("pos", "cancel", {"operation_key": first["operation_key"]})
        elif case == "reference_commit":
            document = self.ok(a)["document_id"]
            second = self.reserve("qa:reference", document=document)
            a = self.request("cumplimiento", "reference", second)
            b = self.request("pos", "commit", {"operation_key": first["operation_key"]})
        rows, proof = self.race(case + ("-reverse" if reverse else "-forward"), [a, b], reverse)
        state = self.worker(action="inspect")
        counts, usage = state["counts"], state["usage"]
        statuses = [row["status"] for row in rows]
        self.expect(usage["consumed"] + usage["reserved"] <= 1, "Shared document capacity was exceeded.")
        self.expect(usage["remaining"] == 1 - usage["consumed"] - usage["reserved"], "Remaining capacity is inconsistent.")
        if case in ("last_slot", "conflicting_operation"):
            loser = 429 if case == "last_slot" else 409
            self.expect(statuses == ([loser, 200] if reverse else [200, loser]), "Wrong admission winner.")
            self.expect(counts == {"documents": 1, "reservations": 1, "aliases": 1, "events": 1, "cancellations": 0}, "Rejected admission mutated the ledger.")
        elif case == "same_operation":
            self.expect(statuses == [200, 200], "Idempotent replay was rejected.")
            self.expect(rows[0]["body"] == rows[1]["body"], "Replay changed the receipt.")
            self.expect(counts == {"documents": 1, "reservations": 1, "aliases": 1, "events": 1, "cancellations": 0}, "Replay duplicated usage.")
        elif case == "fiscal_alias":
            self.expect(statuses == [200, 200], "Shared fiscal identity was rejected.")
            self.expect(rows[0]["body"]["document_id"] == rows[1]["body"]["document_id"], "Fiscal identity split into two documents.")
            self.expect(counts == {"documents": 1, "reservations": 2, "aliases": 3, "events": 2, "cancellations": 0}, "Fiscal aliases or reservations duplicated.")
            self.expect(usage["reserved"] == 1, "Shared fiscal document must hold one slot.")
        elif case == "commit_cancel":
            self.expect(statuses == ([409, 200] if reverse else [200, 409]), "Settlement did not choose one terminal outcome.")
            self.expect(usage["consumed"] == (0 if reverse else 1) and usage["reserved"] == 0, "Settlement changed consumption incorrectly.")
            self.expect(counts["cancellations"] == int(reverse) and counts["events"] == 2, "Settlement duplicated events or cancellation.")
            self.expect([row["state"] for row in state["reservations"]] == ["released" if reverse else "committed"], "Wrong terminal reservation state.")
        elif case == "reserve_cancel":
            self.expect(statuses == ([409, 200] if reverse else [200, 200]), "Delayed reserve bypassed cancellation.")
            self.expect(usage["consumed"] == usage["reserved"] == 0, "Cancellation left an occupied slot.")
            self.expect(counts["documents"] == counts["reservations"] == (0 if reverse else 1), "Cancelled work created unexpected records.")
            self.expect(counts["cancellations"] == 1, "Cancellation tombstone is missing.")
            denied = self.worker(**a)
            self.expect(denied["status"] == 409, "Replay resurrected a cancelled operation.")
        elif case == "reference_commit":
            self.expect(statuses == ([200, 200] if reverse else [409, 200]), "Reference accepted an unconfirmed origin.")
            self.expect(counts["reservations"] == (2 if reverse else 1) and usage["consumed"] == 1, "Reference changed origin consumption.")
            self.ok(a)
            after = self.worker(action="inspect")
            self.expect(after["counts"]["reservations"] == 2 and after["counts"]["events"] == 3, "Reference retry duplicated its result.")
        elif case == "shared_commit_cancel":
            self.expect(statuses == [200, 200], "Independent origins could not settle.")
            self.expect(usage["consumed"] == 1 and usage["reserved"] == 0, "Cancelling one origin refunded a committed document.")
            self.expect(counts["documents"] == 1 and counts["events"] == 4 and counts["cancellations"] == 1, "Shared settlement duplicated the ledger.")
            released = self.ok(self.request("ecf", "reconcile", {"operation_key": second["operation_key"]}))
            self.expect(released["state"] == "released" and released["consumed"], "Released origin lost shared consumption.")
        return {"case": case, "order": "reverse" if reverse else "forward", "status": "passed",
                "assertions": self.assertions - before, "http_statuses": statuses, "state": state, "proof": proof}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    mode = parser.add_mutually_exclusive_group(required=True)
    mode.add_argument("--runtime", help="Existing /tmp/bys-mysql-check-<16 hex> server; never starts MySQL")
    mode.add_argument("--sqlite-self-test", action="store_true")
    mode.add_argument("--cleanup", type=Path)
    parser.add_argument("--case", choices=CASES)
    parser.add_argument("--order", choices=("forward", "reverse"))
    args = parser.parse_args()
    os.umask(0o077)
    if args.cleanup:
        check(re.fullmatch(r"/tmp/lauda-business-[a-f0-9]{16}", str(args.cleanup)) is not None, "Invalid cleanup path.")
        marker = INFRA.runtime("manifest", args.cleanup)
        check(marker["purpose"] == "business-f5-mysql", "Cleanup requires an F5 run.")
        INFRA.runtime("preflight", marker["server"])
        with open(Path(marker["server"]) / "verification.lock", "a") as lock:
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
            removed = INFRA.runtime("cleanup", args.cleanup)
        print(json.dumps({"removed": removed}))
        return 0
    base = Path("/tmp/lauda-business-" + secrets.token_hex(8))
    base.mkdir(mode=0o700)
    mysql = bool(args.runtime)
    selected = [args.case] if args.case else list(CASES)
    orders = [args.order == "reverse"] if args.order else [False, True]
    report = {"status": "not_verified", "mysql_verified": False, "sqlite_self_test_verified": False,
              "scope": "central_commercial_admission", "mode": "mysql" if mysql else "sqlite_self_test",
              "runtime": str(base), "cases": [], "selected_cases": selected, "expected_cases": len(selected) * len(orders),
              "cleanup": [], "limits": ["Does not certify local application adapters, native MySQL schemas in other projects, browser acceptance or deployment."]}
    lock = None
    try:
        (base / "synthetic-only").write_text("Business commercial QA only")
        write(base / "commercial-qa.json", {"purpose": "business-commercial-qa", "base": str(base), "mode": report["mode"]})
        for project in INFRA.REPOS:
            (base / project).mkdir(mode=0o700)
        report["sources_before"] = sources()
        if mysql:
            report["server"] = INFRA.runtime("preflight", args.runtime)
            lock = open(Path(args.runtime) / "verification.lock", "a")
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
            write(base / "mysql-run.json", {"purpose": "business-f5-mysql", "base": str(base), "server": args.runtime})
            report["prepared"] = INFRA.runtime("prepare", base)
        verification = Verification(base, mysql)
        report["setup"] = verification.worker(action="setup")
        for case in selected:
            for reverse in orders:
                report["cases"].append(verification.case(case, reverse))
                print(f"Passed: {case} / {'reverse' if reverse else 'forward'}", flush=True)
        report["assertions"] = verification.assertions
        report["status"] = "passed" if mysql else "self_test_passed"
    except (Exception, KeyboardInterrupt) as error:
        report["error"] = str(error)
    finally:
        for process in list(INFRA.PROCESSES):
            INFRA.stop(process)
        if mysql and (base / "mysql-run.json").exists():
            try:
                report["cleanup"] = INFRA.runtime("cleanup", base)
            except Exception as error:
                report["cleanup_error"] = str(error)
                report["status"] = "not_verified"
        if lock is not None:
            lock.close()
        report["sources_after"] = sources()
        if report.get("sources_before") != report["sources_after"]:
            report["status"] = "not_verified"
            report["source_error"] = "Sources changed during verification."
        if mysql and report["status"] == "passed":
            if len(report["cleanup"]) != 5:
                report["status"] = "not_verified"
                report["cleanup_error"] = "Expected five disposable schemas and users removed."
            else:
                report["selected_cases_verified"] = True
                report["mysql_verified"] = len(report["cases"]) == len(CASES) * 2
        report["sqlite_self_test_verified"] = not mysql and report["status"] == "self_test_passed"
        write(base / "result.json", report)
        result = ROOT / ".lauda-reports/business-20261106" / f"f6-commercial-concurrency-{base.name.removeprefix('lauda-business-')}.json"
        result.parent.mkdir(parents=True, exist_ok=True)
        write(result, report)
        print(json.dumps({"status": report["status"], "mysql_verified": report["mysql_verified"],
                          "cases": len(report["cases"]), "report": str(result), "error": report.get("error")}))
    return 0 if report["status"] in ("passed", "self_test_passed") else 2


if __name__ == "__main__":
    signal.signal(signal.SIGTERM, lambda *_: (_ for _ in ()).throw(InterruptedError("Verification interrupted.")))
    raise SystemExit(main())
