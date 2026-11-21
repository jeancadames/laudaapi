#!/usr/bin/env python3
"""Run central Business MySQL checks from a server terminal using BYS isolation."""
import argparse
import json
import os
from pathlib import Path
import re
import signal
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[3]
BYS = ROOT.parent / "byslaudaapi"
PREFLIGHT = ROOT / "tests/Fixtures/BusinessIdentity/mysql_runtime.php"
RUNNER = Path(__file__).with_name("mysql_check.py")


def prepare_and_start():
    prepared = subprocess.run(
        ["php", str(BYS / "scripts/bys_mysql_check.php"), "--prepare"],
        cwd=BYS, check=True, capture_output=True, text=True, timeout=30,
    )
    result = json.loads(prepared.stdout)
    runtime = result.get("runtime", "")
    if (result.get("status") != "prepared_only"
            or re.fullmatch(r"/tmp/bys-mysql-check-[a-f0-9]{16}", runtime) is None
            or result.get("start") != runtime + "/start.sh"):
        raise RuntimeError("BYS no devolvió una preparación descartable válida.")
    print(f"Instancia de prueba: {runtime}", flush=True)
    print("Inicializando MySQL aislado; límite de arranque: 150 segundos.", flush=True)
    startup = {"runtime": runtime, "startup_verified": False, "timeout_seconds": 150}
    try:
        with open(Path(runtime) / "business-startup.log", "w") as log:
            process = subprocess.Popen([runtime + "/start.sh"], stdout=log,
                                       stderr=subprocess.STDOUT, start_new_session=True)
            try:
                code = process.wait(timeout=150)
            except (subprocess.TimeoutExpired, KeyboardInterrupt):
                os.killpg(process.pid, signal.SIGTERM)
                try:
                    process.wait(timeout=5)
                except subprocess.TimeoutExpired:
                    os.killpg(process.pid, signal.SIGKILL)
                    process.wait(timeout=5)
                startup["interrupted_or_timed_out"] = True
                startup["exit_code"] = process.returncode
                raise RuntimeError("El arranque se interrumpió. Conserva los logs y usa --start-new para otro intento; no reutilices este datadir.")
            startup["exit_code"] = code
            if code != 0:
                raise RuntimeError(f"MySQL no arrancó (salida {code}). Consulta {runtime}/initialize.log y server.log.")
        subprocess.run(["php", str(PREFLIGHT), "preflight", runtime], cwd=ROOT, check=True, timeout=30)
        startup["startup_verified"] = True
    finally:
        Path(runtime, "business-startup.json").write_text(json.dumps(startup, indent=2) + "\n")
    return runtime


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    mode = parser.add_mutually_exclusive_group(required=True)
    mode.add_argument("--start-new", action="store_true", help="Preparar una instancia BYS nueva, arrancarla y probarla")
    mode.add_argument("--runtime", help="Reutilizar una instancia BYS ya disponible, sin reinicializarla")
    args = parser.parse_args()
    os.umask(0o077)
    try:
        if args.start_new:
            runtime = prepare_and_start()
        else:
            runtime = args.runtime
            subprocess.run(["php", str(PREFLIGHT), "preflight", runtime], cwd=ROOT, check=True, timeout=30)
        print("Ejecutando 22 intercalados centrales de cupos/documentos con conexiones MySQL independientes.", flush=True)
        code = subprocess.run([sys.executable, str(RUNNER), "--runtime", runtime], cwd=ROOT).returncode
        print(f"Resultado del ejecutor: {code}. Evidencia: {ROOT}/.lauda-reports/business-20261106/", flush=True)
        print(f"La instancia queda disponible para las siguientes comprobaciones: --runtime={runtime}", flush=True)
        print("Este grupo no certifica adaptadores nativos, finanzas F3/F4, navegador ni despliegue.", flush=True)
        return code
    except (OSError, ValueError, RuntimeError, subprocess.SubprocessError) as error:
        print(f"No se completó la verificación MySQL: {error}", file=sys.stderr)
        return 2


if __name__ == "__main__":
    raise SystemExit(main())
