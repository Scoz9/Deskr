import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { link } from '@/routes/portal';

type Props = {
    /** The form was sent: the same answer whether the address is known or not. */
    linkRequested: boolean;
    /** Somebody arrived here from a link that had run out. */
    linkExpired: boolean;
};

/**
 * The door to "my requests": an address goes in, a link comes back by email.
 *
 * It is also where an expired link lands, because whoever clicked it is
 * somebody the helpdesk wants to hear from: what they need is a fresh link, not
 * a refusal (§5).
 */
export default function PortalAccess({ linkRequested, linkExpired }: Props) {
    const { t } = useTranslation();
    const [email, setEmail] = useState('');
    const [error, setError] = useState<string | undefined>(undefined);
    const [sending, setSending] = useState(false);

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        if (email.trim() === '') {
            setError(t('support.errors.required'));

            return;
        }

        setError(undefined);
        setSending(true);

        router.post(
            link.url(),
            { email },
            {
                onError: (errors) => setError(errors.email),
                onFinish: () => setSending(false),
            },
        );
    };

    return (
        <>
            <Head title={t('portal.title')} />

            <div className="flex min-h-svh flex-col items-center bg-background p-6 md:p-10">
                <div className="w-full max-w-md space-y-8">
                    <header className="space-y-2">
                        <h1 className="text-2xl font-medium">
                            {t('portal.title')}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {t('portal.description')}
                        </p>
                    </header>

                    {linkExpired && (
                        <p
                            role="status"
                            className="rounded-md border border-amber-600/40 bg-amber-50 p-4 text-sm dark:bg-amber-950/40"
                        >
                            {t('portal.expired')}
                        </p>
                    )}

                    {linkRequested ? (
                        <p
                            role="status"
                            className="rounded-md border border-emerald-600/40 bg-emerald-50 p-4 text-sm dark:bg-emerald-950/40"
                        >
                            {t('portal.sent')}
                        </p>
                    ) : (
                        <form
                            onSubmit={submit}
                            className="grid gap-6"
                            noValidate
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="email">
                                    {t('support.fields.email')}
                                </Label>
                                <Input
                                    id="email"
                                    name="email"
                                    type="email"
                                    autoComplete="email"
                                    maxLength={255}
                                    aria-invalid={error !== undefined}
                                    value={email}
                                    onChange={(event) =>
                                        setEmail(event.target.value)
                                    }
                                />
                                <InputError message={error} />
                            </div>

                            <Button
                                type="submit"
                                disabled={sending}
                                className="justify-self-start"
                            >
                                {t('portal.submit')}
                            </Button>
                        </form>
                    )}
                </div>
            </div>
        </>
    );
}
