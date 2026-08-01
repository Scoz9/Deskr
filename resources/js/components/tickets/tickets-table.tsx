import {
    MaterialReactTable,
    useMaterialReactTable,
} from 'material-react-table';
import type {
    MRT_ColumnDef,
    MRT_PaginationState,
    MRT_Updater,
} from 'material-react-table';
import { MRT_Localization_IT } from 'material-react-table/locales/it';
import { useMemo } from 'react';
import { Badge } from '@/components/ui/badge';

export type TicketStatus =
    | 'nuovo'
    | 'assegnato'
    | 'in_lavorazione'
    | 'in_attesa'
    | 'risolto'
    | 'chiuso'
    | 'annullato';

export type TicketPriority = 'bassa' | 'normale' | 'alta' | 'urgente';

export type TicketChannel = 'web' | 'email' | 'telefono';

export type TicketRow = {
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
};

export type PaginatedTickets = {
    data: TicketRow[];
    meta: {
        currentPage: number;
        perPage: number;
        total: number;
    };
};

export const statusLabels: Record<TicketStatus, string> = {
    nuovo: 'Nuovo',
    assegnato: 'Assegnato',
    in_lavorazione: 'In lavorazione',
    in_attesa: 'In attesa risposta',
    risolto: 'Risolto',
    chiuso: 'Chiuso',
    annullato: 'Annullato',
};

const statusVariants: Record<
    TicketStatus,
    'default' | 'secondary' | 'outline' | 'destructive'
> = {
    nuovo: 'default',
    assegnato: 'secondary',
    in_lavorazione: 'secondary',
    in_attesa: 'outline',
    risolto: 'secondary',
    chiuso: 'outline',
    annullato: 'destructive',
};

export const priorityLabels: Record<TicketPriority, string> = {
    bassa: 'Bassa',
    normale: 'Normale',
    alta: 'Alta',
    urgente: 'Urgente',
};

const priorityVariants: Record<
    TicketPriority,
    'default' | 'secondary' | 'outline' | 'destructive'
> = {
    bassa: 'outline',
    normale: 'secondary',
    alta: 'default',
    urgente: 'destructive',
};

export const channelLabels: Record<TicketChannel, string> = {
    web: 'Web',
    email: 'Email',
    telefono: 'Telefono',
};

const openedAtFormatter = new Intl.DateTimeFormat('it-IT', {
    dateStyle: 'short',
    timeStyle: 'short',
});

type TicketsTableProps = {
    tickets: PaginatedTickets;
    /** Called with the 1-based page number the table wants next. */
    onPageChange: (page: number) => void;
};

/**
 * Must be rendered inside TicketsTableThemeProvider: useMaterialReactTable
 * resolves the MUI theme when the hook runs, not when the table renders.
 *
 * Paginated on the server: the page the table shows is derived from what the
 * server last answered, never from a client-side slice of an array the
 * server sent in full — the backlog here is the one the demo seeder sized
 * specifically to make that mistake obvious. Deriving the pagination state
 * from props instead of mirroring it into local state is also what makes
 * the browser's back and forward buttons work for free.
 *
 * The visit itself is the page's job, not this table's: it has to carry
 * whatever filters are active, and this component has no idea what they are.
 */
export default function TicketsTable({
    tickets,
    onPageChange,
}: TicketsTableProps) {
    const pagination = useMemo<MRT_PaginationState>(
        () => ({
            pageIndex: tickets.meta.currentPage - 1,
            pageSize: tickets.meta.perPage,
        }),
        [tickets.meta.currentPage, tickets.meta.perPage],
    );

    const handlePaginationChange = (
        updater: MRT_Updater<MRT_PaginationState>,
    ): void => {
        const next =
            typeof updater === 'function' ? updater(pagination) : updater;

        if (next.pageIndex === pagination.pageIndex) {
            return;
        }

        onPageChange(next.pageIndex + 1);
    };

    const columns = useMemo<MRT_ColumnDef<TicketRow>[]>(
        () => [
            {
                accessorKey: 'reference',
                header: 'Riferimento',
            },
            {
                accessorKey: 'subject',
                header: 'Oggetto',
            },
            {
                id: 'requester',
                accessorFn: (ticket) =>
                    ticket.organization === null
                        ? ticket.requester
                        : `${ticket.requester} (${ticket.organization})`,
                header: 'Richiedente',
            },
            {
                id: 'team',
                accessorFn: (ticket) => ticket.team ?? '—',
                header: 'Team',
            },
            {
                id: 'status',
                accessorFn: (ticket) => statusLabels[ticket.status],
                header: 'Stato',
                Cell: ({ row }) => (
                    <Badge variant={statusVariants[row.original.status]}>
                        {statusLabels[row.original.status]}
                    </Badge>
                ),
            },
            {
                id: 'priority',
                accessorFn: (ticket) => priorityLabels[ticket.priority],
                header: 'Priorità',
                Cell: ({ row }) => (
                    <Badge variant={priorityVariants[row.original.priority]}>
                        {priorityLabels[row.original.priority]}
                    </Badge>
                ),
            },
            {
                id: 'channel',
                accessorFn: (ticket) => channelLabels[ticket.channel],
                header: 'Canale',
            },
            {
                id: 'assignee',
                accessorFn: (ticket) => ticket.assignee ?? 'Non assegnato',
                header: 'Assegnatario',
            },
            {
                id: 'openedAt',
                accessorFn: (ticket) =>
                    ticket.openedAt === null
                        ? ''
                        : openedAtFormatter.format(new Date(ticket.openedAt)),
                header: 'Aperto il',
            },
        ],
        [],
    );

    const table = useMaterialReactTable({
        columns,
        data: tickets.data,
        localization: MRT_Localization_IT,
        manualPagination: true,
        rowCount: tickets.meta.total,
        onPaginationChange: handlePaginationChange,
        enableColumnPinning: true,
        muiPaginationProps: {
            rowsPerPageOptions: [tickets.meta.perPage],
        },
        state: {
            pagination,
        },
        initialState: {
            density: 'compact',
        },
    });

    return <MaterialReactTable table={table} />;
}
