"""Safety checks that do not start a server or use operational application state."""
import json
import os
from pathlib import Path
import secrets
import shutil
import subprocess
import tempfile
import unittest

HERE = Path(__file__).resolve().parent


class CommercialRunnerSafetyTest(unittest.TestCase):
    def setUp(self):
        self.base = Path('/tmp/lauda-business-' + secrets.token_hex(8))
        self.base.mkdir(mode=0o700)

    def tearDown(self):
        shutil.rmtree(self.base)

    def write(self, name, payload):
        path = self.base / name
        path.write_text(json.dumps(payload))
        path.chmod(0o600)

    def test_cleanup_rejects_foreign_server_before_creating_its_lock_file(self):
        with tempfile.TemporaryDirectory(prefix='business-unrelated-') as server:
            self.write('mysql-run.json', {'purpose': 'business-f5-mysql', 'base': str(self.base), 'server': server})
            result = subprocess.run(['python3', str(HERE / 'mysql_check.py'), '--cleanup=' + str(self.base)],
                                    capture_output=True, text=True, timeout=20)
            self.assertNotEqual(0, result.returncode)
            self.assertFalse((Path(server) / 'verification.lock').exists())
            self.assertIn('runtime privado descartable', result.stderr)

    def test_cleanup_rejects_another_verifiers_runtime(self):
        self.write('mysql-run.json', {'purpose': 'business-f2-mysql', 'base': str(self.base),
                                      'server': '/tmp/bys-mysql-check-' + '0' * 16})
        result = subprocess.run(['python3', str(HERE / 'mysql_check.py'), '--cleanup=' + str(self.base)],
                                capture_output=True, text=True, timeout=20)
        self.assertNotEqual(0, result.returncode)
        self.assertIn('Cleanup requires an F5 run', result.stderr)

    def test_worker_refuses_mysql_to_sqlite_mode_changes_before_booting(self):
        self.write('commercial-qa.json', {'purpose': 'business-commercial-qa', 'base': str(self.base), 'mode': 'mysql'})
        result = subprocess.run(['php', str(HERE / 'mysql_worker.php')],
                                env={**os.environ, 'LAUDA_BUSINESS_RUNTIME': str(self.base), 'LAUDA_BUSINESS_MYSQL': '0'},
                                input=json.dumps({'action': 'setup'}), capture_output=True, text=True, timeout=20)
        self.assertNotEqual(0, result.returncode)
        self.assertIn('mode does not match', result.stderr)
        self.assertFalse((self.base / 'central').exists())


if __name__ == '__main__':
    unittest.main()
