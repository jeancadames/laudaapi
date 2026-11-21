"""TLS loopback proxy to five real PHP kernels with synthetic-only configuration."""
import http.client, http.server, json, os, pathlib, signal, ssl, subprocess, sys, threading, time
base = pathlib.Path(sys.argv[1])
if not (base / 'synthetic-only').is_file() or base.parent != pathlib.Path('/tmp') or not base.name.startswith('lauda-business-'):
    raise RuntimeError('Synthetic runtime required')
fixture = pathlib.Path(__file__).resolve().parent
projects = ['central', 'ecf', 'pos', 'bys', 'cumplimiento']
key, cert = base / 'tls.key', base / 'tls.pem'
if not key.exists():
    subprocess.run(['openssl','req','-x509','-newkey','rsa:2048','-nodes','-days','1','-keyout',str(key),'-out',str(cert),'-subj','/CN=127.0.0.1','-addext','subjectAltName=IP:127.0.0.1'],check=True,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    key.chmod(0o600)
processes=[]; servers=[]
signal.signal(signal.SIGTERM, lambda *_: (_ for _ in ()).throw(KeyboardInterrupt()))
class Proxy(http.server.BaseHTTPRequestHandler):
    def log_message(self, *args): pass
    def handle_request(self):
        conn=http.client.HTTPConnection('127.0.0.1',self.server.target,timeout=30)
        body=self.rfile.read(int(self.headers.get('Content-Length','0')))
        headers={k:v for k,v in self.headers.items() if k.lower() not in ['connection','content-length']}
        headers['Host']='127.0.0.1:'+str(self.server.server_port)
        conn.request(self.command,self.path,body,headers)
        response=conn.getresponse(); data=response.read()
        self.send_response(response.status)
        for k,v in response.getheaders():
            if k.lower() not in ['transfer-encoding','connection','content-length']: self.send_header(k,v)
        self.send_header('Content-Length',str(len(data))); self.end_headers()
        if self.command!='HEAD': self.wfile.write(data)
        conn.close()
    do_GET=do_POST=do_DELETE=do_HEAD=handle_request
try:
    for index, project in enumerate(projects):
        env={**os.environ,'LAUDA_BUSINESS_RUNTIME':str(base),'LAUDA_BUSINESS_PROJECT':project}
        log=open(base/project/'server.log','ab')
        processes.append(subprocess.Popen(['php','-S','127.0.0.1:'+str(9500+index),str(fixture/'router.php')],env=env,stdout=log,stderr=log))
        server=http.server.ThreadingHTTPServer(('127.0.0.1',9440+index),Proxy);server.target=9500+index
        context=ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER);context.load_cert_chain(cert,key)
        server.socket=context.wrap_socket(server.socket,server_side=True); servers.append(server)
        threading.Thread(target=server.serve_forever,daemon=True).start()
    print('Business synthetic HTTPS runtimes: 9440–9444',flush=True)
    while all(p.poll() is None for p in processes): time.sleep(1)
except KeyboardInterrupt:
    pass
finally:
    for server in servers: server.shutdown()
    for process in processes: process.terminate()
