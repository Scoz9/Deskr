/**
 * Copia dei valori di @scrapkit/engineering-kit/prettier, non un `import` da
 * esso: la config del kit fa `import tailwindcss from 'prettier-plugin-tailwindcss'`,
 * ma quel pacchetto è ESM-only e non ha un export default (espone solo
 * `options`, `parsers`, `printers`), quindi il modulo non si linka. Qui il plugin
 * è nominato come stringa, la forma che risolve Prettier stesso.
 *
 * Appena il kit passa a `import * as tailwindcss` o alla forma stringa, questo
 * file torna a essere `{ ...base, tailwindStylesheet }`.
 *
 * @type {import('prettier').Config}
 */
export default {
    semi: true,
    singleQuote: true,
    singleAttributePerLine: false,
    htmlWhitespaceSensitivity: 'css',
    printWidth: 80,
    tabWidth: 4,
    plugins: ['prettier-plugin-tailwindcss'],
    tailwindFunctions: ['clsx', 'cn', 'cva'],
    tailwindStylesheet: 'resources/css/app.css',
    overrides: [
        {
            files: '**/*.yml',
            options: {
                tabWidth: 2,
            },
        },
    ],
};
