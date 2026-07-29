// La configurazione condivisa è il livello base; le voci aggiunte dopo lo
// spread vincono, quindi ignore e override di progetto stanno sotto.
import scrapkit from '@scrapkit/engineering-kit/eslint';

/** @type {import('eslint').Linter.Config[]} */
export default [
    ...scrapkit,
    {
        ignores: [
            'tailwind.config.js',
            'vite.config.ts',
            // Codice generato: Wayfinder, primitive shadcn, file di traduzione.
            'resources/js/actions/**',
            'resources/js/components/ui/*',
            'resources/js/locales/**',
            'resources/js/routes/**',
            'resources/js/wayfinder/**',
        ],
    },
];
