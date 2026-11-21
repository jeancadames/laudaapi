#!/usr/bin/env python3
"""F2-only verifier. Reuses the approved disposable server; never starts MySQL."""
import argparse
import fcntl
import hashlib
import json
import os
from pathlib import Path
import re
import secrets
import signal
import subprocess
import sys
import time
import xml.etree.ElementTree as ET
from urllib.parse import parse_qs, urlsplit

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[2]
REPOS = {"central": "laudaapi", "ecf": "ecflaudaapi", "pos": "poslaudaapi",
         "bys": "byslaudaapi", "cumplimiento": "cumplimientolaudaapi"}
RESULT = ROOT / ".lauda-reports/business-20261106/mysql-result.json"
PROCESSES = set()


def check(condition, message):
    if not condition:
        raise RuntimeError(message)


def stop(process):
    if process.poll() is None:
        process.terminate()
        try:
            process.wait(timeout=5)
        except subprocess.TimeoutExpired:
            process.kill()
            process.wait()
    PROCESSES.discard(process)


def record_process(artifact, command, process, stdout, stderr, timed_out=False):
    if artifact is None:
        return
    # Artifacts live inside the private synthetic runtime; no input or environment is logged.
    for suffix, content in (("stdout.log", stdout), ("stderr.log", stderr),
                            ("process.json", json.dumps({"command": command, "returncode": process.returncode,
                                                        "timed_out": timed_out}, indent=2))):
        path = Path(f"{artifact}.{suffix}")
        path.write_text(content)
        path.chmod(0o600)


def json_output(output, label):
    try:
        return json.loads(output)
    except json.JSONDecodeError as error:
        raise RuntimeError(f"{label}: respuesta JSON inválida. Salida original:\n{output[-4000:]}") from error


def run(command, env=None, data=None, timeout=180, artifact=None):
    process = subprocess.Popen(command, cwd=ROOT, env=env, stdin=subprocess.PIPE,
                               stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
    PROCESSES.add(process)
    stdout, stderr, timed_out = "", "", False
    try:
        try:
            stdout, stderr = process.communicate(data, timeout=timeout)
        except subprocess.TimeoutExpired:
            timed_out = True
            stop(process)
            stdout, stderr = process.communicate()
        check(not timed_out, f"{Path(command[1]).name}: timeout; {(stderr or stdout)[-4000:]}")
        check(process.returncode == 0, f"{Path(command[1]).name}: {(stderr or stdout)[-4000:]}")
        return stdout
    finally:
        stop(process)
        record_process(artifact, command, process, stdout, stderr, timed_out)


def runtime(action, value):
    artifact = Path(value) / action if action in ("prepare", "cleanup") else None
    return json_output(run(["php", str(HERE / "mysql_runtime.php"), action, str(value)], artifact=artifact), f"runtime/{action}")


def sources():
    hashes = {}
    for repo in REPOS.values():
        root = ROOT.parent / repo
        for folder in ("app", "config", "routes", "database/migrations", "tests/Support"):
            for path in sorted((root / folder).rglob("*.php")):
                check(not path.is_symlink(), "Fuente inesperada con enlace simbólico.")
                hashes[str(path.relative_to(ROOT.parent))] = hashlib.sha256(path.read_bytes()).hexdigest()
        for path in [root / "bootstrap/app.php", root / "bootstrap/providers.php", root / "composer.lock"]:
            hashes[str(path.relative_to(ROOT.parent))] = hashlib.sha256(path.read_bytes()).hexdigest()
    for path in sorted(HERE.iterdir()):
        if path.is_file():
            hashes[str(path.relative_to(ROOT.parent))] = hashlib.sha256(path.read_bytes()).hexdigest()
    path = ROOT / "tests/Feature/Billing/BusinessIdentityLifecycleTest.php"
    hashes[str(path.relative_to(ROOT.parent))] = hashlib.sha256(path.read_bytes()).hexdigest()
    return hashes


class Verification:
    def __init__(self, base, mysql, report):
        self.base, self.mysql, self.report = base, mysql, report
        self.sequence = 0

    def artifact(self, project, action):
        self.sequence += 1
        return self.base / f"{self.sequence:03d}-{project}-{action}"

    def env(self, project):
        # Explicit synthetic environment. App helper replaces every database/cache/storage path.
        env = {**os.environ, "LAUDA_BUSINESS_RUNTIME": str(self.base), "LAUDA_BUSINESS_PROJECT": project,
               "LAUDA_BUSINESS_MYSQL": "1" if self.mysql else "0", "APP_ENV": "testing",
               "DB_URL": "", "DB_CONNECTION": "sqlite", "DB_DATABASE": ":memory:"}
        for key in ("APP_CONFIG_CACHE", "APP_ROUTES_CACHE", "APP_EVENTS_CACHE", "APP_SERVICES_CACHE", "APP_PACKAGES_CACHE"):
            env.pop(key, None)
        return env

    def script(self, project, name):
        return run(["php", str(HERE / name)], self.env(project), artifact=self.artifact(project, name))

    def worker(self, project, **action):
        output = run(["php", str(HERE / "mysql_worker.php")], self.env(project), json.dumps(action),
                     artifact=self.artifact(project, action["action"]))
        return json_output(output, f"{project}/{action['action']}")

    def ok(self, project, **action):
        result = self.worker(project, **action)
        check(result["status"] == 200, f"{project}/{action['action']}: HTTP {result['status']}")
        return result["body"]

    def reset(self, *projects):
        for project in projects:
            self.script(project, "reset.php")

    def race(self, name, project, actions, expected, point="lock"):
        if not self.mysql:
            rows = [self.worker(project, **action) for action in actions]
        else:
            processes = []
            recorded = set()
            command = ["php", str(HERE / "mysql_worker.php")]
            try:
                for i, action in enumerate(actions):
                    process = subprocess.Popen(command, cwd=ROOT,
                        env=self.env(project), stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
                    PROCESSES.add(process)
                    processes.append(process)
                    process.stdin.write(json.dumps({**action, "barrier": {"name": name, "worker": i, "point": point}}))
                    process.stdin.close()
                    process.stdin = None
                deadline = time.monotonic() + 25
                while not all((self.base / f"{name}.{i}.ready").exists() for i in range(2)):
                    check(time.monotonic() < deadline, f"{name}: timeout de barrera")
                    for i, process in enumerate(processes):
                        if process.poll() is not None:
                            out, err = process.communicate()
                            record_process(self.base / f"{name}.{i}", command, process, out, err)
                            recorded.add(i)
                            raise RuntimeError(f"{name}: worker terminó antes de la barrera: {(err or out)[-2000:]}")
                    time.sleep(.01)
                (self.base / f"{name}.go").touch(mode=0o600)
                rows = []
                for i, process in enumerate(processes):
                    out, err = process.communicate(timeout=45)
                    record_process(self.base / f"{name}.{i}", command, process, out, err)
                    recorded.add(i)
                    check(process.returncode == 0, f"{name}: {(err or out)[-4000:]}")
                    rows.append(json_output(out, f"{name}/{i}"))
                check(all(row["barrier_reached"] for row in rows), f"{name}: barrera no acreditada")
                check(len({row["connection"]["connection_id"] for row in rows}) == 2, "Conexiones no independientes")
                check(len({row["connection"]["pid"] for row in rows}) == 2, "Procesos no independientes")
                check(all(row["connection"]["driver"] == "mysql" for row in rows), "Motor incorrecto")
            finally:
                for i, process in enumerate(processes):
                    stop(process)
                    if i not in recorded:
                        out, err = process.communicate()
                        record_process(self.base / f"{name}.{i}", command, process, out, err)
        statuses = sorted(row["status"] for row in rows)
        check(statuses in expected, f"{name}: resultados inesperados {statuses}")
        self.report["races"].append({"name": name, "concurrent": self.mysql, "statuses": statuses,
                                     "connections": [row["connection"] for row in rows]})
        print(f"  {name}: {statuses}", flush=True)
        return [row["body"] for row in rows]

    def request_and_proof(self, service="bys", **start):
        request = self.ok("central", action="start", service=service, **start)
        proof = self.ok(service, action="prove", token=request["request_token"])
        return proof["proof_token"]

    def cases(self):
        self.reset("central", "bys")
        rows = self.race("duplicate-start", "central", [{"action": "start"}] * 2, [[200, 200]])
        check(rows[0]["request_token"] == rows[1]["request_token"], "Inicio no idempotente")
        state = self.ok("central", action="state")
        check(state["intents"] == 1 and len(state["links"]) == 1, "Inicio duplicó la reserva")
        proof = self.ok("bys", action="prove", token=rows[0]["request_token"])["proof_token"]
        rows = self.race("duplicate-confirm", "central", [{"action": "confirm", "token": proof}] * 2, [[200, 200]])
        check(rows[0]["activation_token"] == rows[1]["activation_token"], "Confirmación no recuperable")
        state = self.ok("central", action="state")
        check(state["consented"] == 1 and state["accounts"] == 1 and len(state["members"]) == 1, "Confirmación duplicada")

        self.reset("central", "bys")
        proof = self.request_and_proof()
        self.race("restart-confirm", "central", [{"action": "start", "restart": True},
                  {"action": "confirm", "token": proof}], [[200, 200], [200, 409]])
        state = self.ok("central", action="state")
        check(state["intents"] == 2 and state["consented"] <= 1, "Reinicio perdió la generación")
        fresh = self.request_and_proof()
        self.ok("central", action="confirm", token=fresh)
        check(self.worker("central", action="confirm", token=proof)["status"] in (403, 409), "Prueba antigua reactivó generación")
        check(int(self.ok("central", action="state")["members"][0]["generation"]) == 2, "Generación final incorrecta")

        self.reset("central", "bys")
        self.ok("central", action="extra-company")
        one = self.request_and_proof()
        two = self.request_and_proof(user=2, company=2)
        self.race("conflicting-identity", "central", [{"action": "confirm", "token": one},
                  {"action": "confirm", "token": two, "user": 2}], [[200, 409]], point="availability")
        state = self.ok("central", action="state")
        check(sum(bool(link["active"]) for link in state["links"]) == 1 and state["accounts"] == 1
              and state["consented"] == 1 and len(state["members"]) == 1, "Conflicto dejó asociaciones parciales")

        self.reset("central", "bys")
        for service in ("ecf", "pos", "bys", "cumplimiento"):
            proof = self.request_and_proof(service)
            activation = self.ok("central", action="confirm", service=service, token=proof)["activation_token"]
            ready = self.ok(service, action="activate", token=activation)["ready_token"]
            check(self.ok(service, action="activate", token=activation)["ready_token"] == ready, "Activación no recuperable")
            self.ok("central", action="ready", service=service, token=ready)
            url = self.ok("central", action="launch", service=service)["url"]
            token = parse_qs(urlsplit(url).fragment)["token"][0]
            self.race(f"{service}-launch-nonce", service, [{"action": "launch", "token": token}] * 2, [[200, 409]])
            state = self.ok(service, action="state")
            check(state["nonces"] == 1 and state["launched"] == 1, "Apertura se consumió más de una vez")
            proof = self.request_and_proof(service, restart=True)
            activation = self.ok("central", action="confirm", service=service, token=proof)["activation_token"]
            self.race(f"{service}-revoke-activate", service, [{"action": "revoke"},
                      {"action": "activate", "token": activation}], [[200, 200], [200, 409]])
            state = self.ok(service, action="state")
            check(not any(link["active"] for link in state["links"]) and not any(member["active"] for member in state["members"]),
                  "La carrera reactivó el vínculo revocado")
            check(self.worker(service, action="activate", token=activation)["status"] in (403, 409), "Replay deshizo revocación")
            check(self.worker(service, action="launch", token=token)["status"] in (403, 409), "Acceso revocado aceptado")
            self.report["flows"].append(service)

    def lifecycle(self):
        self.ok("central", action="invoices-schema")
        xml = self.base / "lifecycle.xml"
        command = ["php", "vendor/bin/phpunit", "--no-configuration", "--bootstrap", "vendor/autoload.php",
                   "--log-junit", str(xml), "tests/Feature/Billing/BusinessIdentityLifecycleTest.php"]
        output = run(command, self.env("central"), artifact=self.base / "lifecycle")
        (self.base / "lifecycle.log").write_text(output)
        tree = ET.parse(xml)
        cases = tree.findall(".//testcase")
        check(len(cases) == 6 and not any(tree.findall(f".//{tag}") for tag in ("error", "failure", "skipped")), "Suite central incompleta")
        self.report["lifecycle"] = {"tests": 6, "assertions": sum(int(case.get("assertions", 0)) for case in cases)}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    group = parser.add_mutually_exclusive_group(required=True)
    group.add_argument("--runtime", help="/tmp/bys-mysql-check-<16 hex> existente")
    group.add_argument("--cleanup", help="Retira solo los esquemas de una ejecución Business interrumpida")
    group.add_argument("--sqlite-self-test", action="store_true", help="Autoprueba secuencial del verificador; NO acredita MySQL")
    args = parser.parse_args()
    os.umask(0o077)
    signal.signal(signal.SIGTERM, lambda *_: (_ for _ in ()).throw(KeyboardInterrupt()))
    mysql = not args.sqlite_self_test
    base = None
    lock = None
    prepared = False
    report = {"scope": "business-f2", "status": "not_verified", "mysql_verified": False,
              "migrations": {}, "races": [], "flows": [], "lifecycle": {"tests": 0}, "removed": []}
    try:
        if args.cleanup:
            base = Path(args.cleanup)
            check(re.fullmatch(r"/tmp/lauda-business-[a-f0-9]{16}", str(base)) is not None, "Runtime inválido")
            args.runtime = runtime("manifest", base)["server"]
        if mysql:
            # PDO preflight happens before creating schemas or booting any application.
            report["server"] = runtime("preflight", args.runtime)
            lockpath = Path(args.runtime) / "verification.lock"
            check(not lockpath.is_symlink(), "Lock inválido")
            lock = open(lockpath, "a")
            fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        if args.cleanup:
            print(json.dumps({"removed": runtime("cleanup", base)}, indent=2))
            return 0
        base = Path("/tmp/lauda-business-" + secrets.token_hex(8))
        base.mkdir(mode=0o700)
        (base / "synthetic-only").write_text("Business F2 synthetic-only")
        for project in REPOS:
            (base / project).mkdir(mode=0o700)
        report["artifacts"] = str(base)
        report["source_hashes"] = sources()
        if mysql:
            (base / "mysql-run.json").write_text(json.dumps({"purpose": "business-f2-mysql", "base": str(base), "server": args.runtime}))
            prepared = True  # Cleanup also covers a partially completed preparation.
            report["prepared"] = runtime("prepare", base)
        verify = Verification(base, mysql, report)
        for project in REPOS:
            print(f"Migraciones y datos sintéticos: {project}", flush=True)
            result = json_output(verify.script(project, "mysql_migrations.php"), f"{project}/migrations")
            check(result["passed"] and result["driver"] == ("mysql" if mysql else "sqlite"), "Motor o migración incorrectos")
            report["migrations"][project] = result
            verify.script(project, "seed.php")
        if mysql:
            verify.lifecycle()
        verify.cases()
        check(len(report["races"]) == 12 and len(report["flows"]) == 4, "Cobertura incompleta")
        check(sources() == report["source_hashes"], "El código cambió durante la prueba")
        report["status"] = "passed" if mysql else "sqlite_self_test_passed"
    except (Exception, KeyboardInterrupt) as error:
        report["error"] = str(error) or "Interrumpido"
    finally:
        for process in list(PROCESSES):
            stop(process)
        if prepared:
            try:
                report["removed"] = runtime("cleanup", base)
                check(len(report["removed"]) == 5, "Falta retirar algún esquema")
            except Exception as error:
                report["cleanup_error"] = str(error)
                report["status"] = "not_verified"
        if lock:
            lock.close()
    report["mysql_verified"] = report["status"] == "passed" and len(report["removed"]) == 5
    report["finished_at_utc"] = time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime())
    report["limits"] = ["Esquemas sintéticos; no valida el esquema operativo completo de DEV.",
                        "Carreras por servicios reales y adaptador de errores; no son pruebas de navegador.",
                        "No habilita Business ni aplica migraciones operativas."]
    payload = json.dumps(report, indent=2, ensure_ascii=False) + "\n"
    if base and base.exists() and not args.cleanup:
        (base / "mysql-result.json").write_text(payload)
    # A local self-test must never overwrite the MySQL evidence.
    result = RESULT if mysql else RESULT.with_name("mysql-verifier-self-test.json")
    result.parent.mkdir(parents=True, exist_ok=True)
    result.write_text(payload)
    summary = (f"LAUDA Business F2 — {report['status']}\n"
               f"MySQL verificado: {report['mysql_verified']}\n"
               f"Aplicaciones con migraciones verificadas: {len(report['migrations'])}/5\n"
               f"Casos centrales: {report['lifecycle']['tests']}/6\n"
               f"Pares de operaciones comprobados: {len(report['races'])}/12\n"
               f"Flujos bilaterales: {len(report['flows'])}/4\n"
               f"Concurrencia real: {mysql and bool(report['races'])}\n"
               f"Esquemas y usuarios retirados: {len(report['removed'])}/5\n"
               f"Artefactos: {report.get('artifacts', 'no creados')}\n"
               f"Error: {report.get('error', 'ninguno')}\n"
               f"Error de limpieza: {report.get('cleanup_error', 'ninguno')}\n"
               "No aplica migraciones a DEV ni habilita Business.\n")
    result.with_suffix(".txt").write_text(summary)
    print(f"Estado: {report['status']}; MySQL verificado: {report['mysql_verified']}")
    print(f"Resultado: {result}")
    if report.get("error"):
        print(report["error"])
    if report.get("cleanup_error"):
        print(f"Limpieza pendiente: {report['cleanup_error']}")
    return 0 if report["status"] in ("passed", "sqlite_self_test_passed") else 2


if __name__ == "__main__":
    sys.exit(main())
