/**
 * Localiza el contrato v1 de Codex (`docs/autoservicio/contrato-v1.ts`).
 *
 * Mientras el contrato no esté integrado en esta rama, indica su ruta con
 * SELF_SERVICE_CONTRACT. Tras la integración se usa la copia del repositorio.
 */
import { existsSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

export const repositoryRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../..');

export function contractPath() {
    const candidate = process.env.SELF_SERVICE_CONTRACT ?? path.join(repositoryRoot, 'docs/autoservicio/contrato-v1.ts');
    if (!existsSync(candidate))
        throw new Error(
            `No encuentro el contrato v1 en ${candidate}. Define SELF_SERVICE_CONTRACT=/ruta/a/docs/autoservicio/contrato-v1.ts`,
        );
    return path.resolve(candidate);
}
