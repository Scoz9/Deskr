import { describe, expect, it } from 'vitest';
import {
    SUPPORT_FIELD_LIMITS,
    emptySupportRequest,
    validateAttachments,
    validateSupportRequest,
} from '@/lib/support-request';
import type {
    AttachmentLimits,
    SupportRequestValues,
} from '@/lib/support-request';

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

const limits: AttachmentLimits = {
    maxFiles: 2,
    maxBytes: 1024,
    mimeTypes: ['image/png', 'application/pdf'],
};

function file(name: string, type: string, size: number): File {
    return new File([new Uint8Array(size)], name, { type });
}

describe('validateAttachments', () => {
    it('accepts files the helpdesk takes', () => {
        expect(
            validateAttachments(
                [
                    file('errore.png', 'image/png', 512),
                    file('nota.pdf', 'application/pdf', 1024),
                ],
                limits,
            ),
        ).toBeUndefined();
    });

    it('accepts a request with no file at all', () => {
        expect(validateAttachments([], limits)).toBeUndefined();
    });

    it('refuses one file more than the limit', () => {
        const files = [
            file('a.png', 'image/png', 1),
            file('b.png', 'image/png', 1),
            file('c.png', 'image/png', 1),
        ];

        expect(validateAttachments(files, limits)).toBe('tooMany');
    });

    it('refuses a file heavier than the limit', () => {
        expect(
            validateAttachments(
                [file('enorme.pdf', 'application/pdf', 1025)],
                limits,
            ),
        ).toBe('tooLarge');
    });

    it('refuses a type that is not on the whitelist', () => {
        expect(
            validateAttachments(
                [file('script.php', 'application/x-php', 10)],
                limits,
            ),
        ).toBe('type');
    });
});
