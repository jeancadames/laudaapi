"""Quote UI adapter over the existing synthetic Business kernel and build fixture."""
import http.client
import http.server
import json
import os
from pathlib import Path
import secrets
import ssl
import sys
import subprocess
import threading
import time

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[2]
NODE = '/home/laudaerp/.nvm/versions/node/v22.23.3/bin/node'
base = Path('/tmp/lauda-business-' + secrets.token_hex(8))
base.mkdir(mode=0o700)
(base / 'synthetic-only').write_text('Business quote acceptance browser only')
navigation = '--navigation' in sys.argv
transition = '--transition' in sys.argv
renewal = '--renewal' in sys.argv
activation = '--activation' in sys.argv or renewal or transition
implementation = '--implementation' in sys.argv or activation or navigation
checkout = '--checkout' in sys.argv or implementation
env = {**os.environ, 'LAUDA_BUSINESS_RUNTIME': str(base), 'LAUDA_BUSINESS_CHECKOUT_TEST': '1' if checkout else '0', 'LAUDA_BUSINESS_IMPLEMENTATION_TEST': '1' if implementation else '0', 'LAUDA_BUSINESS_ACTIVATION_TEST': '1' if activation else '0'}
env['LAUDA_BUSINESS_TRANSITION_TEST'] = '1' if transition else '0'
env['LAUDA_BUSINESS_RENEWAL_TEST'] = '1' if renewal else '0'
print('Artifacts: ' + str(base), flush=True)
subprocess.run(['php', str(HERE / 'browser.php'), 'seed'], env=env, check=True)
subprocess.run(['php', str(HERE / 'browser.php'), 'wayfinder'], env=env, check=True)
with (base / 'build.log').open('w') as log:
    subprocess.run([NODE, str(HERE.parent / 'BusinessIdentity/build.mjs'), str(ROOT), str(base / 'central'), str(base / 'central/generated')], stdout=log, stderr=log, check=True)
subprocess.run(['openssl', 'req', '-x509', '-newkey', 'rsa:2048', '-nodes', '-days', '1', '-keyout', str(base / 'tls.key'), '-out', str(base / 'tls.pem'), '-subj', '/CN=127.0.0.1', '-addext', 'subjectAltName=IP:127.0.0.1'], check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
(base / 'tls.key').chmod(0o600)


class Proxy(http.server.BaseHTTPRequestHandler):
    protocol_version = 'HTTP/1.1'

    def log_message(self, *args):
        pass

    def handle_request(self):
        connection = http.client.HTTPConnection('127.0.0.1', 9506, timeout=30)
        body = self.rfile.read(int(self.headers.get('Content-Length', '0')))
        headers = {k: v for k, v in self.headers.items() if k.lower() not in ['connection', 'content-length']}
        headers['Host'] = '127.0.0.1:9446'
        connection.request(self.command, self.path, body, headers)
        response = connection.getresponse()
        data = response.read()
        self.send_response(response.status)
        for key, value in response.getheaders():
            if key.lower() not in ['transfer-encoding', 'connection', 'content-length']:
                self.send_header(key, value)
        self.send_header('Content-Length', str(len(data)))
        self.end_headers()
        if self.command != 'HEAD':
            self.wfile.write(data)
        connection.close()

    do_GET = do_POST = do_HEAD = handle_request


class BrowserServer(http.server.ThreadingHTTPServer):
    request_queue_size = 128


server = None
with (base / 'server.log').open('wb') as log:
    php = subprocess.Popen(['php', '-S', '127.0.0.1:9506', str(HERE / 'browser.php')], env=env, stdout=log, stderr=log)
    try:
        server = BrowserServer(('127.0.0.1', 9446), Proxy)
        context = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
        context.load_cert_chain(base / 'tls.pem', base / 'tls.key')
        server.socket = context.wrap_socket(server.socket, server_side=True)
        threading.Thread(target=server.serve_forever, daemon=True).start()
        time.sleep(1)
        subprocess.run([NODE, str(HERE / ('navigation.mjs' if navigation else 'transition.mjs' if transition else 'renewal.mjs' if renewal else 'implementation.mjs' if implementation else 'checkout.mjs' if checkout else 'browser.mjs')), str(base)], env=env, check=True, timeout=240)
        if navigation:
            sys.exit(0)
        result = subprocess.run(['php', str(HERE / 'browser.php'), 'verify'], env=env, capture_output=True, text=True, check=True)
        evidence = json.loads(result.stdout)
        (base / 'persistence-result.json').write_text(json.dumps(evidence, indent=2))
        print(json.dumps(evidence), flush=True)
    finally:
        if server is not None:
            server.shutdown()
            server.server_close()
        php.terminate()
        php.wait(timeout=10)
