// Servidor de la vista previa: Vite con Tailwind y Vue, sin Laravel ni Wayfinder.
//   SELF_SERVICE_CONTRACT=/ruta/docs/autoservicio/contrato-v1.ts \
//     npx vite --config tests/Fixtures/BusinessSelfService/preview/vite.config.mjs
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import os from 'node:os';
import path from 'node:path';
import { contractPath, repositoryRoot as root } from '../contract.mjs';

const contract = contractPath();

export default {
    root,
    publicDir: false,
    cacheDir: path.join(os.tmpdir(), 'lauda-self-service-preview-vite'),
    plugins: [tailwindcss(), vue()],
    resolve: {
        alias: {
            '@self-service-contract': contract,
            '@': path.join(root, 'resources/js'),
        },
    },
    server: {
        host: '127.0.0.1',
        port: 5199,
        strictPort: true,
        fs: { allow: [root, path.dirname(contract)] },
    },
};
