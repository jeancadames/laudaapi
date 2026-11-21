import { writeFileSync, mkdirSync } from 'node:fs';
import path from 'node:path';
const root = process.argv[2], runtime = process.argv[3], generated = process.argv[4];
if (generated && generated !== runtime + '/generated') throw new Error('Generated route directory must belong to the synthetic runtime');
if (!/^\/home\/laudaerp\/laravel\/(laudaapi|ecflaudaapi|poslaudaapi|byslaudaapi|cumplimientolaudaapi)$/.test(root) || !/^\/tmp\/lauda-business-[a-f0-9]{16}\/[a-z]+$/.test(runtime)) throw new Error('Synthetic runtime required');
mkdirSync(runtime, { recursive: true, mode: 0o700 });
// Optionally consume real Wayfinder output produced by the isolated kernel. No operational bootstrap.
const config = path.join(runtime, 'vite.config.mjs');
const aliases = generated ? { '@/routes': generated + '/routes', '@/actions': generated + '/actions', '@/wayfinder': generated + '/wayfinder', '@': root + '/resources/js' } : null;
writeFileSync(config, `import original from ${JSON.stringify(root + '/vite.config.ts')};\nexport default { ...original, root: ${JSON.stringify(root)}, envDir: ${JSON.stringify(runtime)}, publicDir: false, ${aliases ? 'resolve: { ...original.resolve, alias: ' + JSON.stringify(aliases) + ' },' : ''} plugins: original.plugins.flat().filter(p => p?.name !== '@laravel/vite-plugin-wayfinder'), build: { ...original.build, outDir: ${JSON.stringify(runtime + '/public/build')}, emptyOutDir: true }, };`);
process.chdir(root);
const { build } = await import(root + '/node_modules/vite/dist/node/index.js');
await build({ configFile: config, logLevel: 'warn' });
console.log('Built ' + path.basename(root));
