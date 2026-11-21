"""Finance runner isolation regressions; never contacts a database server."""
import json
import os
from pathlib import Path
import secrets
import shutil
import subprocess
import tempfile
import unittest

import mysql_check as verifier

HERE = Path(__file__).resolve().parent


class FinanceRunnerSafetyTest(unittest.TestCase):
    def setUp(self):
        self.base = Path("/tmp/lauda-business-" + secrets.token_hex(8))
        self.base.mkdir(mode=0o700)

    def tearDown(self):
        shutil.rmtree(self.base)

    def write(self, name, payload):
        path = self.base / name
        path.write_text(json.dumps(payload))
        path.chmod(0o600)

    def marker(self, mode="mysql", **changes):
        self.write("finance-qa.json", {"purpose": "business-finance-qa", "base": str(self.base), "mode": mode, **changes})

    def worker(self, mysql):
        return subprocess.run(["php", str(HERE / "mysql_worker.php")],
                              env={**os.environ, "LAUDA_BUSINESS_RUNTIME": str(self.base),
                                   "LAUDA_BUSINESS_MYSQL": "1" if mysql else "0"},
                              input=json.dumps({"action": "setup", "stage": "quote"}),
                              capture_output=True, text=True, timeout=20)

    def test_mode_mismatch_cannot_boot_or_create_a_database(self):
        for mode, mysql in (("mysql", False), ("sqlite_self_test", True)):
            with self.subTest(mode=mode):
                self.marker(mode)
                result = self.worker(mysql)
                self.assertNotEqual(0, result.returncode)
                self.assertIn("mode does not match", result.stderr)
                self.assertFalse((self.base / "central").exists())

    def test_foreign_marker_cannot_recreate_a_schema(self):
        self.marker(purpose="another-suite")
        result = self.worker(True)
        self.assertNotEqual(0, result.returncode)
        self.assertFalse((self.base / "central").exists())

    def test_public_marker_is_rejected_before_boot(self):
        self.marker()
        (self.base / "finance-qa.json").chmod(0o644)
        result = self.worker(True)
        self.assertNotEqual(0, result.returncode)
        self.assertIn("manifiesto privado", result.stderr)
        self.assertFalse((self.base / "central").exists())

    def test_existing_mysql_manifest_cannot_fall_back_to_sqlite(self):
        self.marker("sqlite_self_test")
        (self.base / "synthetic-only").touch(mode=0o600)
        (self.base / "central").mkdir(mode=0o700)
        self.write("central/mysql.json", {})
        result = self.worker(False)
        self.assertNotEqual(0, result.returncode)
        self.assertIn("cannot fall back to SQLite", result.stderr)
        self.assertFalse((self.base / "central/database.sqlite").exists())

    def test_cleanup_refuses_an_admission_run(self):
        self.write("mysql-run.json", {"purpose": "business-f5-mysql", "base": str(self.base),
                                      "server": "/tmp/bys-mysql-check-" + "0" * 16})
        result = subprocess.run(["python3", str(HERE / "mysql_check.py"), "--cleanup", str(self.base)],
                                capture_output=True, text=True, timeout=20)
        self.assertNotEqual(0, result.returncode)
        self.assertIn("Cleanup requires a finance run", result.stderr)

    def test_cleanup_refuses_foreign_server_before_creating_a_lock(self):
        with tempfile.TemporaryDirectory(prefix="finance-unrelated-") as server:
            self.write("mysql-run.json", {"purpose": "business-f5-mysql", "suite": "business-finance",
                                          "base": str(self.base), "server": server})
            result = subprocess.run(["python3", str(HERE / "mysql_check.py"), "--cleanup", str(self.base)],
                                    capture_output=True, text=True, timeout=20)
            self.assertNotEqual(0, result.returncode)
            self.assertFalse((Path(server) / "verification.lock").exists())

    def test_sequential_self_test_never_claims_real_lock_contention(self):
        run = verifier.Verification(self.base, False)
        seen = []

        def worker(**action):
            seen.append(action["number"])
            return {"number": action["number"]}

        run.worker = worker
        rows, proof = run.race("synthetic", [{"number": 1}, {"number": 2}], True, "companies")
        self.assertEqual(seen, [2, 1])
        self.assertEqual(rows, [{"number": 1}, {"number": 2}])
        self.assertEqual(proof, {"concurrent": False, "lock_waits": 0})


if __name__ == "__main__":
    unittest.main()
