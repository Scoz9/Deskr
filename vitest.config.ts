// mergeConfig fa un merge profondo: i valori di progetto vincono sulla base
// condivisa di @scrapkit/engineering-kit.
import { fileURLToPath } from 'node:url';
import base from '@scrapkit/engineering-kit/vitest';
import react from '@vitejs/plugin-react';
import { defineConfig, mergeConfig } from 'vitest/config';

export default mergeConfig(
    base,
    defineConfig({
        plugins: [react()],
        resolve: {
            alias: {
                '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
            },
        },
        test: {
            setupFiles: ['resources/js/test/setup.ts'],
        },
    }),
);
