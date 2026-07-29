/**
 * Native name (autonym) of a locale code, e.g. "Italiano" for "it" and
 * "English" for "en". Derived at runtime via Intl.DisplayNames so new
 * locales added through translations:add-locale need no extra mapping.
 */
export function localeDisplayName(code: string): string {
    const name = new Intl.DisplayNames([code], { type: 'language' }).of(code);

    if (!name || name === code) {
        return code;
    }

    return name.charAt(0).toLocaleUpperCase(code) + name.slice(1);
}
