#!/usr/bin/env python3
"""Contract lifecycle races on the existing disposable MySQL server; never starts it."""
import argparse
import copy
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
SPEC = importlib.util.spec_from_file_location("business_commercial_qa", HERE.parent / "BusinessCommercial/mysql_check.py")
COMM = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(COMM)
INFRA = COMM.INFRA
check, write = COMM.check, COMM.write
CASES = ("accept_repeat", "accept_key_conflict", "checkout_repeat", "checkout_competing_quotes",
         "activate_repeat", "activate_key_conflict", "renewal_propose_repeat", "renewal_propose_conflict",
         "renewal_accept_repeat", "renewal_accept_revise", "renewal_invoice_repeat", "renewal_accept_invoice")


def sources():
    result = COMM.sources()
    for path in sorted(HERE.iterdir()):
        if path.suffix in (".php", ".py"):
            check(not path.is_symlink(), "Unexpected finance source symlink.")
            result[str(path.relative_to(ROOT.parent))] = hashlib.sha256(path.read_bytes()).hexdigest()
    return result


class Verification(COMM.Verification):
    def worker(self, **action):
        output = INFRA.run(["php", str(HERE / "mysql_worker.php")], self.env, json.dumps(action),
                           artifact=self.artifact(action["action"]))
        return INFRA.json_output(output, action["action"])

    def request(self, quote, operation, payload=None):
        return {"action": "request", "quote": quote["public_id"], "operation": operation,
                "payload": payload or {"hash": quote["hash"], "idempotency_key": str(uuid.uuid4())}}

    def race(self, name, actions, reverse, table):
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
            process.stdin.write(json.dumps({**actions[index], "barrier": {"name": name, "role": role, "table": table}}))
            process.stdin.close()
            process.stdin = None
            deadline = time.monotonic() + 10
            marker = Path(f"{prefix}.{role}.ready")
            while not marker.exists():
                check(process.poll() is None, f"Worker exited before {role} barrier; see private process logs.")
                check(time.monotonic() < deadline, f"Timeout at {role} barrier.")
                time.sleep(0.01)
            return json.loads(marker.read_text())

        try:
            holding = start(order[0], "hold")
            waiting = start(order[1], "wait")
            self.expect(holding["driver"] == waiting["driver"] == "mysql", "Both workers must use MySQL.")
            self.expect(holding["connection_id"] != waiting["connection_id"] and holding["pid"] != waiting["pid"],
                        "Workers must have independent processes and connections.")
            self.expect(holding["database"] == waiting["database"], "Workers use different schemas.")
            for connection in (holding, waiting):
                self.expect(connection["isolation_level"] == "REPEATABLE-READ", "Expected MySQL REPEATABLE READ.")
                self.expect("STRICT_TRANS_TABLES" in connection["sql_mode"].split(",")
                            or "STRICT_ALL_TABLES" in connection["sql_mode"].split(","), "Strict SQL is required.")
            lock = self.worker(action="locks", holding=holding["connection_id"], waiting=waiting["connection_id"])
            self.expect(lock["lock_waits"] > 0, "MySQL did not confirm actual lock contention.")
            self.expect(processes[order[1]].poll() is None, "Second worker completed before mutex release.")
            Path(f"{prefix}.go").touch(mode=0o600)
            for index, process in processes.items():
                stdout, stderr = process.communicate(timeout=25)
                INFRA.record_process(artifacts[index], ["php", str(HERE / "mysql_worker.php")], process, stdout, stderr)
                check(process.returncode == 0, f"Finance worker failed: {(stderr or stdout)[-3000:]}")
                rows[index] = INFRA.json_output(stdout, name)
                self.expect(rows[index]["barrier_reached"], "Worker skipped the mutex barrier.")
            return rows, {"concurrent": True, "lock_waits": lock["lock_waits"], "mutex": table,
                          "holding": holding, "waiting": waiting}
        finally:
            for index, process in processes.items():
                INFRA.stop(process)
                if not Path(f"{artifacts[index]}.process.json").exists():
                    stdout, stderr = process.communicate()
                    INFRA.record_process(artifacts[index], ["php", str(HERE / "mysql_worker.php")], process, stdout, stderr)

    def case(self, case, reverse):
        before = self.assertions
        stage = "renewal" if case.startswith("renewal_") else (
            "activation" if case.startswith("activate_") else "checkout" if case.startswith("checkout_") else "quote")
        setup = self.worker(action="setup", stage=stage, second_quote=case in ("accept_key_conflict", "checkout_competing_quotes"))
        quote = setup["quotes"][0]
        table = "service_catalogs" if stage == "quote" else "companies"
        offer = None
        if stage in ("quote", "checkout"):
            a = self.request(quote, "accept" if stage == "quote" else "checkout")
            b = copy.deepcopy(a)
            if case == "accept_key_conflict":
                b = self.request(setup["quotes"][1], "accept", {"hash": setup["quotes"][1]["hash"],
                                                              "idempotency_key": a["payload"]["idempotency_key"]})
            elif case == "checkout_competing_quotes":
                b = self.request(setup["quotes"][1], "checkout")
            elif case == "checkout_repeat":
                b["payload"]["idempotency_key"] = str(uuid.uuid4())
        elif stage == "activation":
            a = self.request(quote, "activate", setup["activation"])
            b = copy.deepcopy(a)
            if case == "activate_key_conflict":
                b["payload"]["technical_evidence"]["pos"] = "Otra evidencia sintética con la misma clave."
            else:
                b["payload"]["idempotency_key"] = str(uuid.uuid4())
        else:
            a = self.request(quote, "propose", setup["proposal"])
            if case.startswith("renewal_propose_"):
                b = copy.deepcopy(a)
                if case == "renewal_propose_conflict":
                    b["payload"].update(idempotency_key=str(uuid.uuid4()), discount_cents=25000)
            else:
                offer = self.ok(a)
                consent = {"confirmed": True, "idempotency_key": str(uuid.uuid4()),
                           "offer_id": offer["id"], "terms_hash": offer["terms_hash"]}
                a = self.request(quote, "renewal_accept", consent)
                b = copy.deepcopy(a)
                b["payload"]["idempotency_key"] = str(uuid.uuid4())
                if case == "renewal_accept_revise":
                    b = self.request(quote, "propose", {**setup["proposal"], "expected_revision": 1,
                                                       "idempotency_key": str(uuid.uuid4()), "discount_cents": 25000})
                elif case == "renewal_accept_invoice":
                    b["operation"] = "renewal_invoice"
                elif case == "renewal_invoice_repeat":
                    self.ok(a)
                    a["operation"] = b["operation"] = "renewal_invoice"
        baseline = self.worker(action="inspect")
        rows, proof = self.race(case + ("-reverse" if reverse else "-forward"), [a, b], reverse, table)
        statuses = [row["status"] for row in rows]
        conflicts = ("accept_key_conflict", "checkout_competing_quotes", "activate_key_conflict",
                     "renewal_propose_conflict", "renewal_accept_revise")
        expected = ([409, 200] if reverse else [200, 409]) if case in conflicts else [200, 200]
        if case == "renewal_accept_invoice" and reverse:
            expected = [200, 409]
        self.expect(statuses == expected, f"{case}: expected {expected}, received {rows}")
        state = self.worker(action="inspect")
        counts = state["counts"]
        if stage in ("quote", "checkout", "activation"):
            event = {"quote": "business.quote.accepted", "checkout": "business.quote.checkout_created",
                     "activation": "business.contract.activated"}[stage]
            expected_audits = {event: 1}
        elif case.startswith("renewal_propose_") or (case == "renewal_accept_revise" and reverse):
            expected_audits = {"business.renewal.offered": 1}
        elif case == "renewal_invoice_repeat":
            expected_audits = {"business.renewal.invoiced": 1}
        else:
            expected_audits = {"business.renewal.accepted": 1}
            if case == "renewal_accept_invoice" and not reverse:
                expected_audits["business.renewal.invoiced"] = 1
        audit_delta = {event: int(state["audits"].get(event, 0)) - int(baseline["audits"].get(event, 0))
                       for event in state["audits"].keys() | baseline["audits"].keys()}
        self.expect({event: count for event, count in audit_delta.items() if count} == expected_audits,
                    "Concurrent operations duplicated or lost audit events.")
        if case == "renewal_accept_revise":
            self.expect(counts["business_renewal_offers"] == (2 if reverse else 1), "Wrong proposal revision count.")
            self.expect(counts["business_renewal_acceptances"] == (0 if reverse else 1), "Stale terms were accepted.")
        else:
            table_counts = {"quote": ("service_quote_acceptances", 1), "checkout": ("service_quote_checkouts", 1),
                            "activation": ("business_contracts", 1)}
            if stage in table_counts:
                table_name, expected_count = table_counts[stage]
            else:
                table_name = ("business_renewal_offers" if case.startswith("renewal_propose_")
                              else "business_renewal_periods" if case == "renewal_invoice_repeat"
                              else "business_renewal_acceptances")
                expected_count = 1
            self.expect(counts[table_name] == expected_count, "Duplicate or missing financial record.")
        if case.endswith("_repeat"):
            self.expect(rows[0]["body"]["id"] == rows[1]["body"]["id"], "Replay created another financial record.")
        if stage == "quote":
            self.expect(counts["invoices"] == counts["payments"] == counts["subscriptions"] == 0, "Acceptance created financial effects.")
        if stage == "checkout":
            self.expect(counts["invoices"] == 1 and counts["invoice_items"] == 2, "Checkout duplicated invoice or contractual lines.")
            self.expect(counts["payments"] == counts["business_contracts"] == counts["subscriptions"] == 0, "Checkout fabricated payment or access.")
        if stage in ("activation", "renewal"):
            self.expect(counts["payments"] == 1, "Activation or renewal generated another payment.")
            self.expect(counts["business_contracts"] == counts["subscriptions"] == 1, "Contract/subscription duplicated.")
            self.expect(counts["business_contract_items"] == counts["subscription_items"] == 4, "Service coverage duplicated.")
            self.expect(state["contract_hashes"] == [quote["hash"]], "Activation changed the accepted price snapshot.")
        issued_renewal = case == "renewal_invoice_repeat" or (case == "renewal_accept_invoice" and not reverse)
        self.expect(counts["invoices"] == (0 if stage == "quote" else 2 if issued_renewal else 1), "Unexpected invoice count.")
        self.expect(counts["business_renewal_periods"] == int(issued_renewal), "Renewal was invoiced without acceptance.")
        if state["invoices"]:
            initial = state["invoices"][0]
            self.expect([initial[k] for k in ("subtotal_cents", "tax_cents", "total_cents")] == [950000, 171000, 1121000],
                        "Initial recurring/implementation amount or taxes changed.")
            self.expect(initial["paid_cents"] == (1121000 if stage in ("activation", "renewal") else 0), "Initial payment changed.")
        if issued_renewal:
            renewal = state["invoices"][1]
            self.expect([renewal[k] for k in ("subtotal_cents", "tax_cents", "total_cents", "paid_cents")] == [300000, 54000, 354000, 0],
                        "Renewal discount, tax, one-time exclusion or unpaid balance is wrong.")
            self.expect(state["renewal_periods"][0]["terms_hash"] == offer["terms_hash"], "Renewal invoice lost accepted terms.")
        # Successful retries must leave both financial rows and the audit trail unchanged.
        for action, row in zip((a, b), rows):
            if row["status"] == 200:
                replay = self.ok(action)
                self.expect(replay["id"] == row["body"]["id"], "Retry returned another record.")
        after = self.worker(action="inspect")
        for field in ("counts", "audits", "invoices", "renewal_periods", "renewal_offers", "renewal_acceptances", "contract_hashes"):
            self.expect(after[field] == state[field], f"Replay mutated {field}.")
        recovered = None
        if case == "renewal_accept_invoice" and reverse:
            receipt = self.ok(b)
            self.expect(self.ok(b)["id"] == receipt["id"], "Recovery retry duplicated the renewal invoice.")
            recovered = self.worker(action="inspect")
            self.expect(recovered["counts"]["invoices"] == 2 and recovered["counts"]["business_renewal_periods"] == 1,
                        "Invoice retry after acceptance did not recover exactly once.")
            self.expect(int(recovered["audits"].get("business.renewal.invoiced", 0)) == 1,
                        "Invoice recovery duplicated its audit event.")
            self.expect(recovered["renewal_periods"][0]["terms_hash"] == offer["terms_hash"]
                        and recovered["invoices"][1]["total_cents"] == 354000
                        and recovered["invoices"][1]["paid_cents"] == 0,
                        "Recovery changed terms, totals or fabricated payment.")
        return {"case": case, "order": "reverse" if reverse else "forward", "status": "passed",
                "assertions": self.assertions - before, "http_statuses": statuses,
                "baseline": baseline, "state": state, "recovery": recovered, "proof": proof}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    mode = parser.add_mutually_exclusive_group(required=True)
    mode.add_argument("--runtime", help="Existing disposable /tmp/bys-mysql-check-<16 hex> instance")
    mode.add_argument("--sqlite-self-test", action="store_true", help="Sequential harness checks only; does not certify MySQL")
    mode.add_argument("--cleanup", type=Path)
    parser.add_argument("--case", choices=CASES)
    parser.add_argument("--order", choices=("forward", "reverse"))
    args = parser.parse_args()
    os.umask(0o077)
    if args.cleanup:
        check(re.fullmatch(r"/tmp/lauda-business-[a-f0-9]{16}", str(args.cleanup)) is not None, "Invalid cleanup path.")
        marker = INFRA.runtime("manifest", args.cleanup)
        check(marker.get("suite") == "business-finance", "Cleanup requires a finance run.")
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
              "scope": "central_financial_contract_lifecycle", "mode": "mysql" if mysql else "sqlite_self_test",
              "runtime": str(base), "cases": [], "selected_cases": selected, "expected_cases": len(selected) * len(orders),
              "cleanup": [], "limits": ["Does not certify payment/reversal races, standalone transitions, native adapters, browser or deployment.",
                                        "Service-layer races; does not replace HTTP authorization/CSRF tests."]}
    lock = None
    try:
        (base / "synthetic-only").write_text("Business finance QA only")
        write(base / "finance-qa.json", {"purpose": "business-finance-qa", "base": str(base), "mode": report["mode"]})
        for project in INFRA.REPOS:
            (base / project).mkdir(mode=0o700)
        report["sources_before"] = sources()
        if mysql:
            report["server"] = INFRA.runtime("preflight", args.runtime)
            lock = open(Path(args.runtime) / "verification.lock", "a")
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
            # Same guarded schema allocator as admission QA, plus a dedicated suite marker.
            write(base / "mysql-run.json", {"purpose": "business-f5-mysql", "suite": "business-finance",
                                             "base": str(base), "server": args.runtime})
            report["prepared"] = INFRA.runtime("prepare", base)
        verification = Verification(base, mysql)
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
        result = ROOT / ".lauda-reports/business-20261106" / f"f6-finance-concurrency-{base.name.removeprefix('lauda-business-')}.json"
        result.parent.mkdir(parents=True, exist_ok=True)
        write(result, report)
        print(json.dumps({"status": report["status"], "mysql_verified": report["mysql_verified"],
                          "cases": len(report["cases"]), "assertions": report.get("assertions"),
                          "report": str(result), "error": report.get("error")}))
    return 0 if report["status"] in ("passed", "self_test_passed") else 2


if __name__ == "__main__":
    signal.signal(signal.SIGTERM, lambda *_: (_ for _ in ()).throw(InterruptedError("Verification interrupted.")))
    raise SystemExit(main())
