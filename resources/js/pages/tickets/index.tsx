import { Head, Link, router } from '@inertiajs/react';
import TicketsFilters from '@/components/tickets/tickets-filters';
import type {
    FilterOption,
    TicketFilters,
} from '@/components/tickets/tickets-filters';
import TicketsSearch from '@/components/tickets/tickets-search';
import TicketsTable from '@/components/tickets/tickets-table';
import type { PaginatedTickets } from '@/components/tickets/tickets-table';
import TicketsTableThemeProvider from '@/components/tickets/tickets-table-theme-provider';
import { Button } from '@/components/ui/button';
import {
    create as ticketsCreate,
    index as ticketsIndex,
} from '@/routes/tickets';

type TicketsProps = {
    tickets: PaginatedTickets;
    filters: TicketFilters;
    filterOptions: {
        teams: FilterOption[];
        assignees: FilterOption[];
    };
    canCreate: boolean;
};

/**
 * Builds the query string from the filters that are actually set: an empty
 * `?status=&team_id=` would still be a filtered view as far as the browser's
 * address bar is concerned, and a bookmark or a shared link should say
 * "everything" the same way visiting the page fresh does.
 */
function queryFrom(
    filters: TicketFilters,
    page?: number,
): Record<string, string | number> {
    const query: Record<string, string | number> = {};

    if (filters.status !== null) {
        query.status = filters.status;
    }

    if (filters.priority !== null) {
        query.priority = filters.priority;
    }

    if (filters.channel !== null) {
        query.channel = filters.channel;
    }

    if (filters.teamId !== null) {
        query.team_id = filters.teamId;
    }

    if (filters.assignee !== null) {
        query.assignee = filters.assignee;
    }

    if (filters.search !== null) {
        query.search = filters.search;
    }

    if (page !== undefined && page > 1) {
        query.page = page;
    }

    return query;
}

export default function Tickets({
    tickets,
    filters,
    filterOptions,
    canCreate,
}: TicketsProps) {
    const visit = (query: Record<string, string | number>): void => {
        router.get(
            ticketsIndex.url({ query }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                only: ['tickets', 'filters', 'filterOptions'],
            },
        );
    };

    // A changed filter always starts back from page one: the page a filter
    // used to sit on says nothing about where its results begin now.
    const handleFilterChange = (patch: Partial<TicketFilters>): void => {
        visit(queryFrom({ ...filters, ...patch }));
    };

    const handlePageChange = (page: number): void => {
        visit(queryFrom(filters, page));
    };

    return (
        <>
            <Head title="Ticket" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div className="flex flex-wrap items-end gap-3">
                        <TicketsSearch
                            value={filters.search}
                            onChange={(search) =>
                                handleFilterChange({ search })
                            }
                        />
                        <TicketsFilters
                            filters={filters}
                            teams={filterOptions.teams}
                            assignees={filterOptions.assignees}
                            onChange={handleFilterChange}
                        />
                    </div>
                    {canCreate && (
                        <Button asChild>
                            <Link href={ticketsCreate.url()}>Nuovo ticket</Link>
                        </Button>
                    )}
                </div>
                <TicketsTableThemeProvider>
                    <TicketsTable
                        tickets={tickets}
                        onPageChange={handlePageChange}
                    />
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
