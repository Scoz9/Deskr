/**
 * The fields of the public intake form, and the rules the browser checks before
 * anybody waits for the server.
 *
 * The rules live here and not in the page so that they can be read and tested
 * on their own. They are a courtesy, not a defence: the request that opens a
 * ticket is validated again server-side at step 23, where the honeypot is also
 * decided on.
 */

export const SUPPORT_FIELD_LIMITS = {
    name: 255,
    email: 255,
    subject: 255,
    body: 5000,
} as const;

export type SupportRequestValues = {
    name: string;
    email: string;
    categoryId: string;
    subject: string;
    body: string;
    /** The honeypot: bait for scripts, never shown to a person. */
    website: string;
};

export type SupportRequestError = 'required' | 'email' | 'tooLong';

export type SupportRequestErrors = Partial<
    Record<keyof SupportRequestValues, SupportRequestError>
>;

/**
 * An address is checked for the shape a person can get wrong — a missing @, a
 * missing domain, a space in the middle. Whether it exists is not something a
 * regular expression knows, and the confirmation email of step 25 answers it
 * for real.
 */
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export function emptySupportRequest(): SupportRequestValues {
    return {
        name: '',
        email: '',
        categoryId: '',
        subject: '',
        body: '',
        website: '',
    };
}

/**
 * Every field that is wrong, with what is wrong about it — all of them at once,
 * so the person fixes the form in one pass instead of discovering the next
 * problem after each attempt.
 */
export function validateSupportRequest(
    values: SupportRequestValues,
): SupportRequestErrors {
    const errors: SupportRequestErrors = {};

    const name = values.name.trim();
    const email = values.email.trim();
    const subject = values.subject.trim();
    const body = values.body.trim();

    if (name === '') {
        errors.name = 'required';
    } else if (name.length > SUPPORT_FIELD_LIMITS.name) {
        errors.name = 'tooLong';
    }

    if (email === '') {
        errors.email = 'required';
    } else if (!EMAIL_PATTERN.test(email)) {
        errors.email = 'email';
    } else if (email.length > SUPPORT_FIELD_LIMITS.email) {
        errors.email = 'tooLong';
    }

    if (values.categoryId === '') {
        errors.categoryId = 'required';
    }

    if (subject === '') {
        errors.subject = 'required';
    } else if (subject.length > SUPPORT_FIELD_LIMITS.subject) {
        errors.subject = 'tooLong';
    }

    if (body === '') {
        errors.body = 'required';
    } else if (body.length > SUPPORT_FIELD_LIMITS.body) {
        errors.body = 'tooLong';
    }

    return errors;
}
