import { describe, expect, it } from 'vitest';
import {
    SUPPORT_FIELD_LIMITS,
    emptySupportRequest,
    validateSupportRequest,
} from '@/lib/support-request';
import type { SupportRequestValues } from '@/lib/support-request';

function values(
    overrides: Partial<SupportRequestValues> = {},
): SupportRequestValues {
    return {
        ...emptySupportRequest(),
        name: 'Anna Rossi',
        email: 'anna.rossi@example.com',
        categoryId: '3',
        subject: 'La stampante non risponde',
        body: 'Da stamattina la stampante del secondo piano non stampa.',
        ...overrides,
    };
}

describe('validateSupportRequest', () => {
    it('accepts a request with every field filled in', () => {
        expect(validateSupportRequest(values())).toEqual({});
    });

    it.each([
        ['name'],
        ['email'],
        ['categoryId'],
        ['subject'],
        ['body'],
    ] as const)('asks for %s when it is missing', (field) => {
        expect(validateSupportRequest(values({ [field]: '' }))).toEqual({
            [field]: 'required',
        });
    });

    it('treats a field of blanks as missing', () => {
        expect(validateSupportRequest(values({ subject: '   ' }))).toEqual({
            subject: 'required',
        });
    });

    it.each([
        ['anna.rossi'],
        ['anna.rossi@'],
        ['@example.com'],
        ['anna rossi@example.com'],
    ])('refuses %s as an email address', (email) => {
        expect(validateSupportRequest(values({ email }))).toEqual({
            email: 'email',
        });
    });

    it.each([['name'], ['email'], ['subject'], ['body']] as const)(
        'refuses a %s longer than the column can hold',
        (field) => {
            const tooLong = 'a'.repeat(SUPPORT_FIELD_LIMITS[field] + 1);
            const overrides =
                field === 'email'
                    ? {
                          email: `${'a'.repeat(SUPPORT_FIELD_LIMITS.email)}@example.com`,
                      }
                    : { [field]: tooLong };

            expect(validateSupportRequest(values(overrides))[field]).toBe(
                'tooLong',
            );
        },
    );

    it('reports every field at once, so the person fixes the form in one pass', () => {
        expect(
            validateSupportRequest(
                values({ name: '', email: '', subject: '' }),
            ),
        ).toEqual({
            name: 'required',
            email: 'required',
            subject: 'required',
        });
    });

    /*
     * The honeypot is not a field anybody sees, so it is never a reason to tell
     * the person their form is wrong. What a filled one means is the server's
     * business, at step 23.
     */
    it('never complains about the honeypot, filled or empty', () => {
        expect(
            validateSupportRequest(values({ website: 'https://spam.example' })),
        ).toEqual({});
    });
});
