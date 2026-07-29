import { describe, expect, it } from 'vitest';
import { localeDisplayName } from '@/lib/locale-display-name';

describe('localeDisplayName', () => {
    it('returns the autonym of a known locale, capitalized', () => {
        expect(localeDisplayName('it')).toBe('Italiano');
        expect(localeDisplayName('en')).toBe('English');
    });

    it('falls back to the code when the locale has no display name', () => {
        expect(localeDisplayName('zzz')).toBe('zzz');
    });
});
