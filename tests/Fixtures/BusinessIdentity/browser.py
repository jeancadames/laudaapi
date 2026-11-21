"""Keep the local servers and browser in the same command network namespace."""
import os,pathlib,subprocess,sys,time
base=pathlib.Path(sys.argv[1]);fixture=pathlib.Path(__file__).resolve().parent
if not (base/'synthetic-only').is_file():raise RuntimeError('Synthetic runtime required')
for project in ['central','ecf','pos','bys','cumplimiento']:
    subprocess.run(['php',str(fixture/'reset.php')],env={**os.environ,'LAUDA_BUSINESS_RUNTIME':str(base),'LAUDA_BUSINESS_PROJECT':project},check=True)
log=open(base/'https-server.log','ab')
server=subprocess.Popen(['python3',str(fixture/'serve.py'),str(base)],stdout=log,stderr=log)
try:
    time.sleep(2)
    env={**os.environ,'NO_UPDATE_NOTIFIER':'1'}
    result=subprocess.run(['/home/laudaerp/.nvm/versions/node/v22.23.3/bin/node',str(fixture/'browser.mjs'),str(base)],env=env)
    sys.exit(result.returncode)
finally:
    server.terminate();server.wait(timeout=10)
