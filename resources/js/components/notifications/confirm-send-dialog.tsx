import { useState } from 'react';
import { toast } from 'sonner';
import TemplatePreview from '@/components/notifications/template-preview';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { notificationKit } from '@/lib/notification-kit/api';
import type { OutboxMessage } from '@/lib/notification-kit/types';

type ConfirmSendDialogProps = {
    message: OutboxMessage | null;
    onOpenChange: (open: boolean) => void;
    onDecided: () => void;
};

/**
 * Shown before a confirmable email leaves: recipients, subject and a preview
 * of exactly what will be sent.
 */
export default function ConfirmSendDialog({
    message,
    onOpenChange,
    onDecided,
}: ConfirmSendDialogProps) {
    const [processing, setProcessing] = useState(false);

    const decide = async (outcome: 'approve' | 'cancel') => {
        if (!message) {
            return;
        }

        setProcessing(true);

        try {
            if (outcome === 'approve') {
                await notificationKit.outbox.approve(message.uuid);
                toast.success('Invio confermato.');
            } else {
                await notificationKit.outbox.cancel(message.uuid);
                toast.success('Invio annullato.');
            }

            onDecided();
        } catch (error) {
            toast.error(
                error instanceof Error
                    ? error.message
                    : 'Operazione non riuscita.',
            );
        } finally {
            setProcessing(false);
        }
    };

    return (
        <Dialog open={message !== null} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Confermi l&apos;invio?</DialogTitle>
                    <DialogDescription>
                        Questa email richiede una conferma manuale prima di
                        partire.
                    </DialogDescription>
                </DialogHeader>

                {message && (
                    <div className="flex flex-col gap-4">
                        <div className="grid gap-3 text-sm">
                            <div>
                                <p className="font-medium text-muted-foreground">
                                    Destinatari
                                </p>
                                {message.recipients.map((recipient) => (
                                    <p
                                        key={`${recipient.type}-${recipient.address}`}
                                    >
                                        <span className="text-muted-foreground uppercase">
                                            {recipient.type}
                                        </span>{' '}
                                        {recipient.name
                                            ? `${recipient.name} <${recipient.address}>`
                                            : recipient.address}
                                    </p>
                                ))}
                            </div>
                            <div>
                                <p className="font-medium text-muted-foreground">
                                    Oggetto
                                </p>
                                <p>{message.rendered_subject}</p>
                            </div>
                        </div>

                        <TemplatePreview
                            html={message.rendered_body_html}
                            className="h-64"
                        />
                    </div>
                )}

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        disabled={processing}
                        onClick={() => void decide('cancel')}
                    >
                        Annulla invio
                    </Button>
                    <Button
                        type="button"
                        disabled={processing}
                        onClick={() => void decide('approve')}
                    >
                        Conferma e invia
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
