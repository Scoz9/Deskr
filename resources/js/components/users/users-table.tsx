import { Pause, Pencil, Play } from 'lucide-react';
import {
    MaterialReactTable,
    useMaterialReactTable,
} from 'material-react-table';
import type { MRT_ColumnDef } from 'material-react-table';
import { MRT_Localization_IT } from 'material-react-table/locales/it';
import { useMemo } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import CreateUserDialog from '@/components/users/create-user-dialog';
import { useCan } from '@/hooks/use-can';
import type { AssignableRole, ManagedUser } from '@/types';

type UsersTableProps = {
    users: ManagedUser[];
    roles: AssignableRole[];
    onEdit: (user: ManagedUser) => void;
    onSuspend: (user: ManagedUser) => void;
    onUnsuspend: (user: ManagedUser) => void;
};

const suspendedUntilFormatter = new Intl.DateTimeFormat('it-IT', {
    dateStyle: 'short',
    timeStyle: 'short',
});

function suspensionLabel(user: ManagedUser): string {
    if (!user.suspended_until) {
        return 'Sospeso';
    }

    return `Sospeso fino al ${suspendedUntilFormatter.format(new Date(user.suspended_until))}`;
}

/**
 * Must be rendered inside UsersTableThemeProvider: useMaterialReactTable
 * resolves the MUI theme when the hook runs, not when the table renders.
 */
export default function UsersTable({
    users,
    roles,
    onEdit,
    onSuspend,
    onUnsuspend,
}: UsersTableProps) {
    const can = useCan();

    const columns = useMemo<MRT_ColumnDef<ManagedUser>[]>(
        () => [
            {
                accessorKey: 'name',
                header: 'Nome',
            },
            {
                accessorKey: 'email',
                header: 'Email',
            },
            {
                id: 'role',
                accessorFn: (user) => user.role?.name ?? '—',
                header: 'Ruolo',
            },
            {
                id: 'status',
                accessorFn: (user) =>
                    user.is_suspended ? 'Sospeso' : 'Attivo',
                header: 'Stato',
                Cell: ({ row }) =>
                    row.original.is_suspended ? (
                        <Badge variant="destructive">
                            {suspensionLabel(row.original)}
                        </Badge>
                    ) : (
                        <Badge variant="secondary">Attivo</Badge>
                    ),
            },
        ],
        [],
    );

    const table = useMaterialReactTable({
        columns,
        data: users,
        localization: MRT_Localization_IT,
        enableRowActions: true,
        positionActionsColumn: 'last',
        enableColumnPinning: true,
        displayColumnDefOptions: {
            'mrt-row-actions': { header: 'Azioni' },
        },
        initialState: {
            density: 'compact',
            pagination: { pageIndex: 0, pageSize: 10 },
            columnPinning: { right: ['mrt-row-actions'] },
        },
        renderTopToolbarCustomActions: () =>
            can('user:create') ? <CreateUserDialog roles={roles} /> : null,
        renderRowActions: ({ row }) => (
            <div className="flex items-center">
                {row.original.can_update && (
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Modifica ${row.original.name}`}
                        data-test={`edit-user-button-${row.original.id}`}
                        onClick={() => onEdit(row.original)}
                    >
                        <Pencil />
                    </Button>
                )}
                {row.original.can_suspend && !row.original.is_suspended && (
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Sospendi ${row.original.name}`}
                        data-test={`suspend-user-button-${row.original.id}`}
                        onClick={() => onSuspend(row.original)}
                    >
                        <Pause />
                    </Button>
                )}
                {row.original.can_suspend && row.original.is_suspended && (
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Riattiva ${row.original.name}`}
                        data-test={`unsuspend-user-button-${row.original.id}`}
                        onClick={() => onUnsuspend(row.original)}
                    >
                        <Play />
                    </Button>
                )}
            </div>
        ),
    });

    return <MaterialReactTable table={table} />;
}
