import { router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

import localesConfig from '../locales/config.json';

/**
 * Current locale plus a switcher that keeps i18next, the <html lang>
 * attribute, the Laravel session (via PUT /locale) and the current
 * Inertia page in sync.
 */
export function useLocale() {
    const { i18n } = useTranslation();

    const setLocale = async (locale: string): Promise<void> => {
        if (
            !localesConfig.locales.includes(locale) ||
            locale === i18n.language
        ) {
            return;
        }

        await i18n.changeLanguage(locale);
        document.documentElement.lang = locale;

        router.put(
            '/locale',
            { locale },
            { preserveScroll: true, preserveState: true },
        );
    };

    return {
        locale: i18n.language,
        locales: localesConfig.locales,
        setLocale,
    };
}
