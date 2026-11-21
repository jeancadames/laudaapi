"""Regression tests for preserving verifier errors; no Laravel or databases are started."""
import json
from pathlib import Path
import sys
import tempfile
import unittest

import mysql_check as verifier


class ProcessDiagnosticsTest(unittest.TestCase):
    def test_nonzero_exit_preserves_original_error_and_private_logs(self):
        with tempfile.TemporaryDirectory(prefix="business-runner-") as directory:
            artifact = Path(directory) / "failure"
            command = [sys.executable, "-c", "import sys; print('before failure'); sys.stderr.write('SQL error 1553'); sys.exit(2)"]
            with self.assertRaisesRegex(RuntimeError, "SQL error 1553"):
                verifier.run(command, artifact=artifact)
            self.assertEqual(Path(f"{artifact}.stdout.log").read_text(), "before failure\n")
            self.assertEqual(Path(f"{artifact}.stderr.log").read_text(), "SQL error 1553")
            self.assertEqual(json.loads(Path(f"{artifact}.process.json").read_text())["returncode"], 2)
            self.assertEqual(Path(f"{artifact}.stderr.log").stat().st_mode & 0o777, 0o600)
            self.assertFalse(verifier.PROCESSES)

    def test_console_exception_with_zero_exit_keeps_its_original_diagnostic(self):
        with tempfile.TemporaryDirectory(prefix="business-runner-") as directory:
            artifact = Path(directory) / "console-error"
            output = verifier.run([sys.executable, "-c", "print('\\n   SQL error 1553: cannot drop index')"], artifact=artifact)
            with self.assertRaisesRegex(RuntimeError, "SQL error 1553: cannot drop index"):
                verifier.json_output(output, "central/migrations")
            self.assertEqual(Path(f"{artifact}.stdout.log").read_text(), output)

    def test_extra_console_output_is_not_accepted_as_successful_json(self):
        with self.assertRaises(RuntimeError):
            verifier.json_output('SQL failure\n{"passed": true}', "migration")

    def test_timeout_preserves_output_and_reaps_the_process(self):
        with tempfile.TemporaryDirectory(prefix="business-runner-") as directory:
            artifact = Path(directory) / "timeout"
            command = [sys.executable, "-c", "import time; print('started', flush=True); time.sleep(30)"]
            with self.assertRaisesRegex(RuntimeError, "timeout"):
                verifier.run(command, timeout=1, artifact=artifact)
            self.assertEqual(Path(f"{artifact}.stdout.log").read_text(), "started\n")
            result = json.loads(Path(f"{artifact}.process.json").read_text())
            self.assertTrue(result["timed_out"])
            self.assertIsNotNone(result["returncode"])
            self.assertFalse(verifier.PROCESSES)

    def test_valid_json_response_is_returned_unchanged(self):
        self.assertEqual(verifier.json_output('{"passed": true, "checks": ["rollback"]}\n', "migration"),
                         {"passed": True, "checks": ["rollback"]})


if __name__ == "__main__":
    unittest.main()
