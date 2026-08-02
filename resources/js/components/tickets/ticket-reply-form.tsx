import { router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import TicketMessageController from '@/actions/App/Http/Controllers/TicketMessageController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { validateAttachments } from '@/lib/support-request';
import type { AttachmentError, AttachmentLimits } from '@/lib/support-request';

const BODY_MAX_LENGTH = 5000;

type TicketReplyFormProps = {
    ticketId: number;
    attachmentLimits: AttachmentLimits;
};

/**
 * The compose box of step 36: one form for both the reply the requester
 * reads and the note the team keeps to itself, a checkbox apart — the same
 * fact with a flag on it that `NewReply` already models (§4), not two forms
 * that would have to be kept in step the day one of them grows a field the
 * other needs too.
 */
export default function TicketReplyForm({
    ticketId,
    attachmentLimits,
}: TicketReplyFormProps) {
    const [body, setBody] = useState('');
    const [isInternal, setIsInternal] = useState(false);
    const [attachments, setAttachments] = useState<File[]>([]);
    const [bodyError, setBodyError] = useState<string | undefined>(undefined);
    const [attachmentError, setAttachmentError] = useState<
        AttachmentError | undefined
    >(undefined);
    const [refusals, setRefusals] = useState<Record<string, string>>({});
    const [sending, setSending] = useState(false);

    const attachmentMessage = (): string | undefined => {
        if (attachmentError === 'tooMany') {
            return `Puoi allegare al massimo ${attachmentLimits.maxFiles} file.`;
        }

        if (attachmentError === 'tooLarge') {
            return `Ogni file può arrivare al massimo a ${Math.round(attachmentLimits.maxBytes / (1024 * 1024))} MB.`;
        }

        if (attachmentError === 'type') {
            return 'Uno dei file scelti non è tra i formati ammessi.';
        }

        const refused = Object.entries(refusals).find(
            ([field]) =>
                field === 'attachments' || field.startsWith('attachments.'),
        );

        return refused?.[1];
    };

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        const trimmed = body.trim();
        const refusedFile = validateAttachments(attachments, attachmentLimits);

        setBodyError(trimmed === '' ? 'Scrivi un messaggio.' : undefined);
        setAttachmentError(refusedFile);
        setRefusals({});

        if (trimmed === '' || refusedFile !== undefined) {
            return;
        }

        setSending(true);

        router.post(
            TicketMessageController.store.url(ticketId),
            { body: trimmed, is_internal: isInternal, attachments },
            {
                preserveScroll: true,
                onError: (serverErrors) => setRefusals(serverErrors),
                onSuccess: () => {
                    setBody('');
                    setIsInternal(false);
                    setAttachments([]);
                },
                onFinish: () => setSending(false),
            },
        );
    };

    return (
        <form onSubmit={submit} className="grid gap-4" noValidate>
            <div className="grid gap-2">
                <Label htmlFor="reply-body">Rispondi</Label>
                <Textarea
                    id="reply-body"
                    name="body"
                    rows={4}
                    maxLength={BODY_MAX_LENGTH}
                    aria-invalid={bodyError !== undefined}
                    value={body}
                    onChange={(event) => setBody(event.target.value)}
                />
                <InputError message={bodyError ?? refusals.body} />
            </div>

            <div className="flex items-center gap-2">
                <Checkbox
                    id="reply-is-internal"
                    checked={isInternal}
                    onCheckedChange={(checked) =>
                        setIsInternal(checked === true)
                    }
                />
                <Label htmlFor="reply-is-internal" className="font-normal">
                    Nota interna — il richiedente non la vedrà
                </Label>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="reply-attachments">Allegati</Label>
                <Input
                    key={attachments.length === 0 ? 'empty' : 'picked'}
                    id="reply-attachments"
                    name="attachments"
                    type="file"
                    multiple
                    accept={attachmentLimits.mimeTypes.join(',')}
                    aria-invalid={attachmentMessage() !== undefined}
                    onChange={(event) =>
                        setAttachments(Array.from(event.target.files ?? []))
                    }
                />
                <InputError message={attachmentMessage()} />
            </div>

            <Button
                type="submit"
                disabled={sending}
                className="justify-self-start"
            >
                {isInternal ? 'Aggiungi nota interna' : 'Invia risposta'}
            </Button>
        </form>
    );
}
