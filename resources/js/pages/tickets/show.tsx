import { Head, setLayoutProps } from '@inertiajs/react';
import TicketActions from '@/components/tickets/ticket-actions';
import {
    channelLabels,
    priorityLabels,
    statusLabels,
} from '@/components/tickets/tickets-table';
import type {
    TicketChannel,
    TicketPriority,
    TicketStatus,
} from '@/components/tickets/tickets-table';
import { Badge } from '@/components/ui/badge';
import { index as ticketsIndex, show as ticketShow } from '@/routes/tickets';

type Attachment = {
    name: string;
    url: string;
};

type Message = {
    id: number;
    body: string;
    isInternal: boolean;
    author: string;
    writtenAt: string | null;
    attachments: Attachment[];
};

type TicketDetail = {
    id: number;
    reference: string;
    subject: string;
    status: TicketStatus;
    priority: TicketPriority;
    channel: TicketChannel;
    requester: string;
    organization: string | null;
    team: string | null;
    assignee: string | null;
    openedAt: string | null;
    messages: Message[];
};

type Props = {
    ticket: TicketDetail;
    nextStatuses: TicketStatus[];
    canUpdate: boolean;
};

const dateFormatter = new Intl.DateTimeFormat('it-IT', {
    dateStyle: 'short',
    timeStyle: 'short',
});

const readableDate = (value: string | null): string =>
    value === null ? '' : dateFormatter.format(new Date(value));

/**
 * The thread of a single ticket, as the console sees it: every reply and
 * every internal note in one timeline, the notes visually set apart so an
 * operator never mistakes a note kept to the team for something the
 * requester has already read (§4 — the portal page renders only the public
 * side of the same thread, and has nothing to distinguish).
 */
export default function TicketShow({ ticket, nextStatuses, canUpdate }: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Ticket', href: ticketsIndex() },
            { title: ticket.reference, href: ticketShow.url(ticket.id) },
        ],
    });

    return (
        <>
            <Head title={`${ticket.reference} — ${ticket.subject}`} />

            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4">
                <header className="space-y-2">
                    <p className="font-mono text-sm text-muted-foreground">
                        {ticket.reference}
                    </p>
                    <h1 className="text-2xl font-medium">{ticket.subject}</h1>
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge>{statusLabels[ticket.status]}</Badge>
                        <Badge variant="secondary">
                            {priorityLabels[ticket.priority]}
                        </Badge>
                        <Badge variant="outline">
                            {channelLabels[ticket.channel]}
                        </Badge>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {ticket.requester}
                        {ticket.organization !== null &&
                            ` (${ticket.organization})`}
                        {ticket.team !== null && ` · ${ticket.team}`}
                        {' · '}
                        {ticket.assignee ?? 'Non assegnato'}
                        {ticket.openedAt !== null &&
                            ` · aperto il ${readableDate(ticket.openedAt)}`}
                    </p>
                </header>

                {canUpdate && (
                    <TicketActions
                        ticketId={ticket.id}
                        priority={ticket.priority}
                        nextStatuses={nextStatuses}
                    />
                )}

                <ol className="space-y-4">
                    {ticket.messages.map((message) => (
                        <li
                            key={message.id}
                            className={
                                message.isInternal
                                    ? 'rounded-md border border-amber-400/60 bg-amber-50 p-4 text-sm dark:border-amber-400/30 dark:bg-amber-950/40'
                                    : 'rounded-md border p-4 text-sm'
                            }
                        >
                            <div className="flex items-center gap-2">
                                <p className="font-medium">{message.author}</p>
                                {message.isInternal && (
                                    <Badge
                                        variant="outline"
                                        className="border-amber-500 text-amber-700 dark:text-amber-400"
                                    >
                                        Nota interna
                                    </Badge>
                                )}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                {readableDate(message.writtenAt)}
                            </p>
                            <p className="mt-2 whitespace-pre-line">
                                {message.body}
                            </p>

                            {message.attachments.length > 0 && (
                                <ul className="mt-3 space-y-1">
                                    {message.attachments.map((attachment) => (
                                        <li key={attachment.url}>
                                            <a
                                                href={attachment.url}
                                                className="text-sm underline underline-offset-4"
                                            >
                                                {attachment.name}
                                            </a>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </li>
                    ))}
                </ol>
            </div>
        </>
    );
}
