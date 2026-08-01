import { Head } from '@inertiajs/react';
import TicketsTable from '@/components/tickets/tickets-table';
import type { PaginatedTickets } from '@/components/tickets/tickets-table';
import TicketsTableThemeProvider from '@/components/tickets/tickets-table-theme-provider';
import { index as ticketsIndex } from '@/routes/tickets';

type TicketsProps = {
    tickets: PaginatedTickets;
};

export default function Tickets({ tickets }: TicketsProps) {
    return (
        <>
            <Head title="Ticket" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <TicketsTableThemeProvider>
                    <TicketsTable tickets={tickets} />
                </TicketsTableThemeProvider>
            </div>
        </>
    );
}

Tickets.layout = {
    breadcrumbs: [
        {
            title: 'Ticket',
            href: ticketsIndex(),
        },
    ],
};
