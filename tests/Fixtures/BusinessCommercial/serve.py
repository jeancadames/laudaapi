"""Test-only verified TLS proxy to the real central kernel; no operational configuration."""
import http.client
import http.server
import os
import pathlib
import signal
import socket
import ssl
import subprocess
import sys
import threading
import time

base = pathlib.Path(sys.argv[1])
port, target = int(sys.argv[2]), int(sys.argv[3])
fiscal_target = int(sys.argv[4]) if len(sys.argv) > 4 else None
if base.parent != pathlib.Path('/tmp') or not (base / 'synthetic-only').is_file():
    raise RuntimeError('Synthetic runtime required')
fixture = pathlib.Path(__file__).resolve().parent
subprocess.run(['openssl', 'req', '-x509', '-newkey', 'rsa:2048', '-nodes', '-days', '1',
                '-keyout', str(base / 'tls.key'), '-out', str(base / 'tls.pem'),
                '-subj', '/CN=127.0.0.1', '-addext', 'subjectAltName=IP:127.0.0.1'],
               check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
(base / 'tls.key').chmod(0o600)

class Proxy(http.server.BaseHTTPRequestHandler):
    def log_message(self, *args):
        pass

    def do_GET(self):
        self.do_POST()

    def do_POST(self):
        is_fiscal = self.path.startswith('/v1/') or ((base / 'facil-browser-enabled').is_file() and not self.path.startswith('/api/business/'))
        destination = fiscal_target if is_fiscal and fiscal_target else target
        connection = http.client.HTTPConnection('127.0.0.1', destination, timeout=30)
        body = self.rfile.read(int(self.headers.get('Content-Length', '0')))
        headers = {k: v for k, v in self.headers.items() if k.lower() not in ['connection', 'content-length']}
        connection.request(self.command, self.path, body, headers)
        response = connection.getresponse()
        data = response.read()
        self.send_response(response.status)
        for key, value in response.getheaders():
            if key.lower() not in ['transfer-encoding', 'connection', 'content-length']:
                self.send_header(key, value)
        self.send_header('Content-Length', str(len(data)))
        self.end_headers()
        self.wfile.write(data)
        connection.close()

server = http.server.ThreadingHTTPServer(('127.0.0.1', port), Proxy)
context = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
context.load_cert_chain(base / 'tls.pem', base / 'tls.key')
server.socket = context.wrap_socket(server.socket, server_side=True)
with open(base / 'commercial-server.log', 'ab') as log:
    process = subprocess.Popen(['php', '-d', 'opcache.enable_cli=1', '-S', f'127.0.0.1:{target}', str(fixture / 'router.php')],
                               env={**os.environ, 'LAUDA_BUSINESS_RUNTIME': str(base)}, stdout=log, stderr=log)
    fiscal_process = subprocess.Popen(['php', '-d', 'opcache.enable_cli=1', '-S', f'127.0.0.1:{fiscal_target}', str(fixture / 'ecf-router.php')],
        env={**os.environ, 'LAUDA_BUSINESS_RUNTIME': str(base)}, stdout=log, stderr=log) if fiscal_target else None
    def stop(*_):
        threading.Thread(target=server.shutdown, daemon=True).start()
    signal.signal(signal.SIGTERM, stop)
    try:
        for child, destination in [(process, target), (fiscal_process, fiscal_target)]:
            if child is None:
                continue
            deadline = time.monotonic() + 8
            while True:
                if child.poll() is not None or time.monotonic() >= deadline:
                    raise RuntimeError('Synthetic PHP server did not become ready')
                try:
                    with socket.create_connection(('127.0.0.1', destination), timeout=0.2):
                        break
                except OSError:
                    time.sleep(0.01)
            probe = http.client.HTTPConnection('127.0.0.1', destination, timeout=25)
            try:
                probe.request('GET', '/up')
                response = probe.getresponse()
                response.read()
                if response.status != 200:
                    raise RuntimeError(f'Synthetic PHP health check at {destination} returned {response.status}')
            finally:
                probe.close()
        (base / 'commercial-server-ready').write_text('ready')
        server.serve_forever(poll_interval=0.1)
    finally:
        server.server_close()
        process.terminate()
        process.wait(timeout=5)
        if fiscal_process:
            fiscal_process.terminate()
            fiscal_process.wait(timeout=5)
