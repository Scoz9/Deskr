import { Head } from '@inertiajs/react';
import { useState } from 'react';
import EditUserDialog from '@/components/users/edit-user-dialog';
import SuspendUserDialog from '@/components/users/suspend-user-dialog';
import UnsuspendUserDialog from '@/components/users/unsuspend-user-dialog';
import UsersTable from '@/components/users/users-table';
import UsersTableThemeProvider from '@/components/users/users-table-theme-provider';
import { index as usersIndex } from '@/routes/users';
import type { AssignableRole, ManagedUser, Organization } from '@/types';

type UsersProps = {
    users: ManagedUser[];
    roles: AssignableRole[];
    organizations: Organization[];
};

export default function Users({ users, roles, organizations }: UsersProps) {
    const [editingUser, setEditingUser] = useState<ManagedUser | null>(null);
    const [suspendingUser, setSuspendingUser] = useState<ManagedUser | null>(
        null,
    );
    const [unsuspendingUser, setUnsuspendingUser] =
        useState<ManagedUser | null>(null);

    return (
        <>
            <Head title="Utenti" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <UsersTableThemeProvider>
                    <UsersTable
                        users={users}
                        roles={roles}
                        organizations={organizations}
                        onEdit={setEditingUser}
                        onSuspend={setSuspendingUser}
                        onUnsuspend={setUnsuspendingUser}
                    />
                </UsersTableThemeProvider>
            </div>

            <EditUserDialog
                user={editingUser}
                roles={roles}
                organizations={organizations}
                onClose={() => setEditingUser(null)}
            />
            <SuspendUserDialog
                user={suspendingUser}
                onClose={() => setSuspendingUser(null)}
            />
            <UnsuspendUserDialog
                user={unsuspendingUser}
                onClose={() => setUnsuspendingUser(null)}
            />
        </>
    );
}

Users.layout = {
    breadcrumbs: [
        {
            title: 'Utenti',
            href: usersIndex(),
        },
    ],
};
