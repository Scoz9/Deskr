import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    SUPPORT_FIELD_LIMITS,
    emptySupportRequest,
    validateSupportRequest,
} from '@/lib/support-request';
import type {
    SupportRequestErrors,
    SupportRequestValues,
} from '@/lib/support-request';
import { store } from '@/routes/support';

type Category = {
    id: number;
    name: string;
};

type Props = {
    categories: Category[];
    /** The ticket just opened, shown once on the way back from a submission. */
    reference: string | null;
};

/**
 * The public intake form: the one surface of the application a person reaches
 * without an account, since a requester never registers (§3).
 *
 * The browser checks the fields before anybody waits for the server, and the
 * server checks them again — the first is a courtesy, the second is the
 * defence. What the server refuses is shown in the server's own words, because
 * some of its rules (the cap on open tickets) are ones the browser has no way
 * to know about.
 */
export default function SupportCreate({ categories, reference }: Props) {
    const { t } = useTranslation();
    const [values, setValues] = useState<SupportRequestValues>(
        emptySupportRequest(),
    );
    const [errors, setErrors] = useState<SupportRequestErrors>({});
    const [refusals, setRefusals] = useState<Record<string, string>>({});
    const [sending, setSending] = useState(false);

    const update = (field: keyof SupportRequestValues, value: string): void => {
        setValues((current) => ({ ...current, [field]: value }));
    };

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        const found = validateSupportRequest(values);

        setErrors(found);
        setRefusals({});

        if (Object.keys(found).length > 0) {
            return;
        }

        setSending(true);

        router.post(store.url(), values, {
            onError: (serverErrors) => setRefusals(serverErrors),
            onSuccess: () => setValues(emptySupportRequest()),
            onFinish: () => setSending(false),
        });
    };

    const errorMessage = (
        field: keyof SupportRequestValues,
    ): string | undefined => {
        const error = errors[field];

        return error === undefined
            ? refusals[field]
            : t(`support.errors.${error}`);
    };

    return (
        <>
            <Head title={t('support.title')} />

            <div className="flex min-h-svh flex-col items-center bg-background p-6 md:p-10">
                <div className="w-full max-w-xl space-y-8">
                    <header className="space-y-2">
                        <h1 className="text-2xl font-medium">
                            {t('support.title')}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {t('support.description')}
                        </p>
                    </header>

                    {reference !== null && (
                        <div
                            role="status"
                            className="rounded-md border border-emerald-600/40 bg-emerald-50 p-4 text-sm dark:bg-emerald-950/40"
                        >
                            <p className="font-medium">
                                {t('support.sent.title')}
                            </p>
                            <p className="text-muted-foreground">
                                {t('support.sent.reference')}{' '}
                                <span className="font-mono">{reference}</span>
                            </p>
                        </div>
                    )}

                    <form onSubmit={submit} className="grid gap-6" noValidate>
                        <div className="grid gap-2">
                            <Label htmlFor="name">
                                {t('support.fields.name')}
                            </Label>
                            <Input
                                id="name"
                                name="name"
                                autoComplete="name"
                                maxLength={SUPPORT_FIELD_LIMITS.name}
                                aria-invalid={errors.name !== undefined}
                                value={values.name}
                                onChange={(event) =>
                                    update('name', event.target.value)
                                }
                            />
                            <InputError message={errorMessage('name')} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="email">
                                {t('support.fields.email')}
                            </Label>
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                autoComplete="email"
                                maxLength={SUPPORT_FIELD_LIMITS.email}
                                aria-invalid={errors.email !== undefined}
                                value={values.email}
                                onChange={(event) =>
                                    update('email', event.target.value)
                                }
                            />
                            <InputError message={errorMessage('email')} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="categoryId">
                                {t('support.fields.category')}
                            </Label>
                            {/*
                             * A native select on purpose: the public portal is a
                             * light surface seen from outside (§5), and this is
                             * the one control every browser already knows how to
                             * render and every assistive technology already knows
                             * how to read.
                             */}
                            <select
                                id="categoryId"
                                name="categoryId"
                                aria-invalid={errors.categoryId !== undefined}
                                value={values.categoryId}
                                onChange={(event) =>
                                    update('categoryId', event.target.value)
                                }
                                className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive md:text-sm"
                            >
                                <option value="">
                                    {t('support.fields.categoryPlaceholder')}
                                </option>
                                {categories.map((category) => (
                                    <option
                                        key={category.id}
                                        value={category.id}
                                    >
                                        {category.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errorMessage('categoryId')} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="subject">
                                {t('support.fields.subject')}
                            </Label>
                            <Input
                                id="subject"
                                name="subject"
                                maxLength={SUPPORT_FIELD_LIMITS.subject}
                                aria-invalid={errors.subject !== undefined}
                                value={values.subject}
                                onChange={(event) =>
                                    update('subject', event.target.value)
                                }
                            />
                            <InputError message={errorMessage('subject')} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="body">
                                {t('support.fields.body')}
                            </Label>
                            <Textarea
                                id="body"
                                name="body"
                                rows={6}
                                maxLength={SUPPORT_FIELD_LIMITS.body}
                                aria-invalid={errors.body !== undefined}
                                value={values.body}
                                onChange={(event) =>
                                    update('body', event.target.value)
                                }
                            />
                            <InputError message={errorMessage('body')} />
                        </div>

                        {/*
                         * The honeypot: in the markup a script reads, out of the
                         * tab order and out of the accessibility tree, so that
                         * nobody it is not meant for ever meets it.
                         */}
                        <div aria-hidden="true" className="hidden">
                            <label htmlFor="website">
                                {t('support.fields.website')}
                            </label>
                            <input
                                id="website"
                                name="website"
                                type="text"
                                tabIndex={-1}
                                autoComplete="off"
                                value={values.website}
                                onChange={(event) =>
                                    update('website', event.target.value)
                                }
                            />
                        </div>

                        <Button
                            type="submit"
                            disabled={sending}
                            className="justify-self-start"
                        >
                            {t('support.submit')}
                        </Button>
                    </form>
                </div>
            </div>
        </>
    );
}
