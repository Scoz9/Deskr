import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type Attachment = {
    name: string;
    url: string;
};

/**
 * The seven statuses of §4, written out so that the label of each one is a key
 * the translation files are checked against: a plain string would let a status
 * added later reach the page with nothing to say about it.
 */
type TicketStatus =
    | 'nuovo'
    | 'assegnato'
    | 'in_lavorazione'
    | 'in_attesa'
    | 'risolto'
    | 'chiuso'
    | 'annullato';

type Message = {
    id: number;
    body: string;
    author: string;
    writtenAt: string | null;
    attachments: Attachment[];
};

type Props = {
    ticket: {
        reference: string;
        subject: string;
        status: TicketStatus;
        openedAt: string | null;
        messages: Message[];
        replyUrl: string;
    };
    /**
     * Whether whoever is looking at this page can post to it: only the
     * portal session can, since a POST needs identity and CSRF, not the
     * signature the confirmation email's link carries (§3).
     */
    canReply: boolean;
};

/**
 * The request as whoever asked sees it: the state it is in and the
 * conversation so far. Read only when reached by the signed link — the
 * portal session is what makes replying possible (§3).
 *
 * There is nothing about who is working on it, which team has it or how it was
 * filed: that is how the helpdesk is organised inside, and it is not what
 * somebody waiting for an answer needs to read.
 */
export default function SupportTicket({ ticket, canReply }: Props) {
    const { t } = useTranslation();
    const [body, setBody] = useState('');
    const [error, setError] = useState<string | undefined>(undefined);
    const [sending, setSending] = useState(false);

    const readableDate = (value: string | null): string =>
        value === null ? '' : new Date(value).toLocaleString();

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        if (body.trim() === '') {
            setError(t('support.errors.required'));

            return;
        }

        setError(undefined);
        setSending(true);

        router.post(
            ticket.replyUrl,
            { body },
            {
                onError: (errors) => setError(errors.body),
                onSuccess: () => setBody(''),
                onFinish: () => setSending(false),
            },
        );
    };

    return (
        <>
            <Head title={`${ticket.reference} — ${ticket.subject}`} />

            <div className="flex min-h-svh flex-col items-center bg-background p-6 md:p-10">
                <div className="w-full max-w-xl space-y-8">
                    <header className="space-y-2">
                        <p className="font-mono text-sm text-muted-foreground">
                            {ticket.reference}
                        </p>
                        <h1 className="text-2xl font-medium">
                            {ticket.subject}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {t(`ticket.status.${ticket.status}`)}
                            {ticket.openedAt !== null && (
                                <> · {readableDate(ticket.openedAt)}</>
                            )}
                        </p>
                    </header>

                    <ol className="space-y-4">
                        {ticket.messages.map((message) => (
                            <li
                                key={message.id}
                                className="rounded-md border p-4 text-sm"
                            >
                                <p className="font-medium">{message.author}</p>
                                <p className="text-xs text-muted-foreground">
                                    {readableDate(message.writtenAt)}
                                </p>
                                <p className="mt-2 whitespace-pre-line">
                                    {message.body}
                                </p>

                                {message.attachments.length > 0 && (
                                    <ul className="mt-3 space-y-1">
                                        {message.attachments.map(
                                            (attachment) => (
                                                <li key={attachment.url}>
                                                    <a
                                                        href={attachment.url}
                                                        className="text-sm underline underline-offset-4"
                                                    >
                                                        {attachment.name}
                                                    </a>
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                )}
                            </li>
                        ))}
                    </ol>

                    {canReply ? (
                        <form
                            onSubmit={submit}
                            className="grid gap-2"
                            noValidate
                        >
                            <Label htmlFor="body">
                                {t('ticket.reply.label')}
                            </Label>
                            <Textarea
                                id="body"
                                name="body"
                                rows={4}
                                maxLength={5000}
                                aria-invalid={error !== undefined}
                                value={body}
                                onChange={(event) =>
                                    setBody(event.target.value)
                                }
                            />
                            <InputError message={error} />

                            <Button
                                type="submit"
                                disabled={sending}
                                className="justify-self-start"
                            >
                                {t('ticket.reply.submit')}
                            </Button>
                        </form>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            {t('ticket.readOnly')}
                        </p>
                    )}
                </div>
            </div>
        </>
    );
}
