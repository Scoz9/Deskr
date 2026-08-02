import { router } from '@inertiajs/react';
import { useState } from 'react';
import TicketController from '@/actions/App/Http/Controllers/TicketController';
import {
    priorityLabels,
    statusLabels,
} from '@/components/tickets/tickets-table';
import type {
    TicketPriority,
    TicketStatus,
} from '@/components/tickets/tickets-table';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

const CANCELLED_STATUS: TicketStatus = 'annullato';

type TicketActionsProps = {
    ticketId: number;
    priority: TicketPriority;
    /** The passages `TicketTransitions` admits from the ticket's current status. */
    nextStatuses: TicketStatus[];
};

type Visitable = { url: string; method: 'post' | 'patch' };

/**
 * The four actions of step 35, all one write away: claim the ticket, move
 * it to any status the lifecycle table admits, change its priority, or
 * cancel it. Cancelling is not a fifth endpoint — `annullato` is a passage
 * like any other in `TicketTransitions`, only pulled out of the status
 * picker into its own button because it is the one an operator should not
 * reach by scrolling a dropdown.
 */
export default function TicketActions({
    ticketId,
    priority,
    nextStatuses,
}: TicketActionsProps) {
    const [processing, setProcessing] = useState(false);

    const visit = (action: Visitable, data?: Record<string, string>): void => {
        setProcessing(true);
        router.visit(action.url, {
            method: action.method,
            data,
            preserveScroll: true,
            onFinish: () => setProcessing(false),
        });
    };

    const assignableStatuses = nextStatuses.filter(
        (status) => status !== CANCELLED_STATUS,
    );
    const canCancel = nextStatuses.includes(CANCELLED_STATUS);

    return (
        <div className="flex flex-wrap items-end gap-3">
            <Button
                type="button"
                variant="secondary"
                disabled={processing}
                onClick={() => visit(TicketController.assignToMe(ticketId))}
            >
                Assegna a me
            </Button>

            {assignableStatuses.length > 0 && (
                <div className="grid w-48 gap-1.5">
                    <Label htmlFor="ticket-next-status">Cambia stato</Label>
                    <Select
                        disabled={processing}
                        value=""
                        onValueChange={(status) =>
                            visit(TicketController.updateStatus(ticketId), {
                                status,
                            })
                        }
                    >
                        <SelectTrigger id="ticket-next-status" size="sm">
                            <SelectValue placeholder="Scegli…" />
                        </SelectTrigger>
                        <SelectContent>
                            {assignableStatuses.map((status) => (
                                <SelectItem key={status} value={status}>
                                    {statusLabels[status]}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            )}

            <div className="grid w-40 gap-1.5">
                <Label htmlFor="ticket-priority">Priorità</Label>
                <Select
                    disabled={processing}
                    value={priority}
                    onValueChange={(value) =>
                        visit(TicketController.updatePriority(ticketId), {
                            priority: value,
                        })
                    }
                >
                    <SelectTrigger id="ticket-priority" size="sm">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {Object.entries(priorityLabels).map(
                            ([value, label]) => (
                                <SelectItem key={value} value={value}>
                                    {label}
                                </SelectItem>
                            ),
                        )}
                    </SelectContent>
                </Select>
            </div>

            {canCancel && (
                <Button
                    type="button"
                    variant="destructive"
                    disabled={processing}
                    onClick={() =>
                        visit(TicketController.updateStatus(ticketId), {
                            status: CANCELLED_STATUS,
                        })
                    }
                >
                    Annulla ticket
                </Button>
            )}
        </div>
    );
}
