import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';

import localesConfig from '../locales/config.json';
import { lazyImportBackend } from './backend';

/**
 * The Blade layout renders <html lang="..."> from app()->getLocale(),
 * which the SetLocale middleware has already resolved. Reading it keeps
 * the initial i18next language in sync with the backend without any
 * extra request. Falls back to the generated default during SSR.
 */
function initialLocale(): string {
    if (typeof document !== 'undefined' && document.documentElement.lang) {
        return document.documentElement.lang;
    }

    return localesConfig.default;
}

export const i18nReady = i18n
    .use(lazyImportBackend)
    .use(initReactI18next)
    .init({
        lng: initialLocale(),
        fallbackLng: localesConfig.fallback,
        supportedLngs: localesConfig.locales,
        defaultNS: localesConfig.defaultNamespace,
        ns: [localesConfig.defaultNamespace],
        interpolation: {
            // React already escapes rendered values.
            escapeValue: false,
        },
    });

export default i18n;
