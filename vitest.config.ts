/**
 * Copia dei valori di @scrapkit/engineering-kit/vitest, non un `mergeConfig` su
 * di esso: il kit pubblica quella config come TypeScript sorgente dentro
 * node_modules, e Node non sa fare type stripping su file lì dentro
 * (ERR_UNSUPPORTED_NODE_MODULES_TYPE_STRIPPING) perché Vite esternalizza le
 * dipendenze invece di bundlarle.
 *
 * Appena il kit pubblica quella config compilata in `.js`/`.mjs`, questo file
 * torna al `mergeConfig(base, …)` documentato nel suo README.
 */
import { fileURLToPath } from 'node:url';
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    plugins: [react()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'jsdom',
        globals: true,
        include: ['resources/js/**/*.test.{ts,tsx}'],
        setupFiles: ['resources/js/test/setup.ts'],
    },
});
