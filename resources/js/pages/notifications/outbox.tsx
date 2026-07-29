import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import ConfirmSendDialog from '@/components/notifications/confirm-send-dialog';
import OutboxStatusBadge from '@/components/notifications/outbox-status-badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { notificationKit } from '@/lib/notification-kit/api';
import type {
    OutboxFilters,
    OutboxMessage,
} from '@/lib/notification-kit/types';
import {
    index as notificationsIndex,
    outbox as outboxRoute,
} from '@/routes/notifications';

export default function Outbox() {
    const [filters, setFilters] = useState<OutboxFilters>({
        status: 'pending',
    });
    const [messages, setMessages] = useState<OutboxMessage[]>([]);
    const [reviewing, setReviewing] = useState<OutboxMessage | null>(null);
    const [loading, setLoading] = useState(true);

    // Bumped from event handlers to pull the queue again after a decision.
    const [refreshToken, setRefreshToken] = useState(0);

    useEffect(() => {
        let cancelled = false;

        notificationKit.outbox
            .list(filters)
            .then((response) => {
                if (!cancelled) {
                    setMessages(response.data);
                    setLoading(false);
                }
            })
            .catch((error: unknown) => {
                if (!cancelled) {
                    setLoading(false);
                    toast.error(
                        error instanceof Error
                            ? error.message
                            : 'Caricamento non riuscito.',
                    );
                }
            });

        return () => {
            cancelled = true;
        };
    }, [filters, refreshToken]);

    return (
        <>
            <Head title="Coda invii" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="grid w-56 gap-1.5">
                    <Label htmlFor="status">Stato</Label>
                    <Select
                        value={filters.status ?? 'all'}
                        onValueChange={(value) =>
                            setFilters({
                                ...filters,
                                status:
                                    value === 'all'
                                        ? undefined
                                        : (value as OutboxFilters['status']),
                            })
                        }
                    >
                        <SelectTrigger id="status">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tutti</SelectItem>
                            <SelectItem value="pending">
                                Da confermare
                            </SelectItem>
                            <SelectItem value="approved">Approvate</SelectItem>
                            <SelectItem value="sent">Inviate</SelectItem>
                            <SelectItem value="cancelled">Annullate</SelectItem>
                            <SelectItem value="failed">Fallite</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Coda di approvazione</CardTitle>
                        <CardDescription>
                            Le email che richiedono una conferma manuale prima
                            di partire.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {loading ? (
                            <Spinner />
                        ) : messages.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Nessun messaggio in questa vista.
                            </p>
                        ) : (
                            <ul className="divide-y">
                                {messages.map((message) => (
                                    <li
                                        key={message.uuid}
                                        className="flex flex-wrap items-center justify-between gap-3 py-3"
                                    >
                                        <div>
                                            <p className="font-medium">
                                                {message.rendered_subject}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {message.recipients
                                                    .map(
                                                        (recipient) =>
                                                            recipient.address,
                                                    )
                                                    .join(', ')}{' '}
                                                —{' '}
                                                {message.template_name ??
                                                    message.template_key}
                                            </p>
                                            {message.error !== null && (
                                                <p className="text-xs text-destructive">
                                                    {message.error}
                                                </p>
                                            )}
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <OutboxStatusBadge
                                                status={message.status}
                                            />
                                            {message.status === 'pending' && (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        setReviewing(message)
                                                    }
                                                >
                                                    Rivedi
                                                </Button>
                                            )}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>

            <ConfirmSendDialog
                message={reviewing}
                onOpenChange={(open) => !open && setReviewing(null)}
                onDecided={() => {
                    setReviewing(null);
                    setRefreshToken((token) => token + 1);
                }}
            />
        </>
    );
}

Outbox.layout = {
    breadcrumbs: [
        {
            title: 'Notifiche',
            href: notificationsIndex(),
        },
        {
            title: 'Coda invii',
            href: outboxRoute(),
        },
    ],
};
