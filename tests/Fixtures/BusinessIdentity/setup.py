"""Prepare a new synthetic runtime; never read deployment env/cache/storage paths."""
import os, pathlib, secrets, subprocess
fixture=pathlib.Path(__file__).resolve().parent
workspace=fixture.parents[3]
base=pathlib.Path('/tmp/lauda-business-'+secrets.token_hex(8));base.mkdir(mode=0o700)
(base/'synthetic-only').write_text('Business identity synthetic integration only')
projects={'central':'laudaapi','ecf':'ecflaudaapi','pos':'poslaudaapi','bys':'byslaudaapi','cumplimiento':'cumplimientolaudaapi'}
node='/home/laudaerp/.nvm/versions/node/v22.23.3/bin/node'
for project,repo in projects.items():
    runtime=base/project;runtime.mkdir(mode=0o700)
    env={**os.environ,'LAUDA_BUSINESS_RUNTIME':str(base),'LAUDA_BUSINESS_PROJECT':project}
    seed=subprocess.run(['php',str(fixture/'seed.php')],env=env,capture_output=True,text=True)
    (runtime/'seed.log').write_text(seed.stdout+seed.stderr)
    if seed.returncode or 'synthetic schema and session ready' not in seed.stdout:
        raise RuntimeError('Synthetic seed failed; inspect '+str(runtime/'seed.log'))
    with (runtime/'build.log').open('w') as log:
        build=subprocess.run([node,str(fixture/'build.mjs'),str(workspace/repo),str(runtime)],stdout=log,stderr=log)
    if build.returncode: raise RuntimeError('Build failed; inspect '+str(runtime/'build.log'))
    print(project+' prepared',flush=True)
print('Runtime: '+str(base))
print('Run: python3 '+str(fixture/'browser.py')+' '+str(base))
