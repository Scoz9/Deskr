import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import RoleController from '@/actions/App/Http/Controllers/RoleController';
import CreateRoleDialog from '@/components/roles/create-role-dialog';
import DeleteRoleDialog from '@/components/roles/delete-role-dialog';
import RenameRoleDialog from '@/components/roles/rename-role-dialog';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useCan } from '@/hooks/use-can';
import { cn } from '@/lib/utils';
import { index as rolesIndex } from '@/routes/roles';
import type { Permission, Role } from '@/types';

type RolesProps = {
    roles: Role[];
    permissions: Permission[];
};

export default function Roles({ roles, permissions }: RolesProps) {
    const can = useCan();
    const [selectedRoleId, setSelectedRoleId] = useState<number | null>(null);
    const [processing, setProcessing] = useState(false);

    const selectedRole =
        roles.find((role) => role.id === selectedRoleId) ?? null;

    const permissionGroups = permissions.reduce<Record<string, Permission[]>>(
        (groups, permission) => {
            const prefix = permission.name.split(':')[0];
            (groups[prefix] ??= []).push(permission);

            return groups;
        },
        {},
    );

    const togglePermission = (permissionName: string, checked: boolean) => {
        if (!selectedRole) {
            return;
        }

        const current = selectedRole.permissions.map(
            (permission) => permission.name,
        );
        const next = checked
            ? [...current, permissionName]
            : current.filter((name) => name !== permissionName);

        router.put(
            RoleController.update.url({ role: selectedRole.id }),
            { permissions: next },
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <>
            <Head title="Ruoli" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="grid gap-4 lg:grid-cols-2">
                    <Card className="order-last lg:order-first">
                        <CardHeader>
                            <CardTitle>Permessi</CardTitle>
                            <CardDescription>
                                {selectedRole
                                    ? `Permessi associati al ruolo "${selectedRole.name}"`
                                    : 'Seleziona un ruolo per gestirne i permessi'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {selectedRole ? (
                                <div className="space-y-6">
                                    {Object.entries(permissionGroups).map(
                                        ([group, groupPermissions]) => (
                                            <div
                                                key={group}
                                                className="space-y-3"
                                            >
                                                <p className="text-sm font-medium text-muted-foreground capitalize">
                                                    {group}
                                                </p>
                                                <div className="grid gap-2">
                                                    {groupPermissions.map(
                                                        (permission) => (
                                                            <div
                                                                key={
                                                                    permission.id
                                                                }
                                                                className="flex items-center gap-2"
                                                            >
                                                                <Checkbox
                                                                    id={`permission-${permission.id}`}
                                                                    checked={selectedRole.permissions.some(
                                                                        (
                                                                            rolePermission,
                                                                        ) =>
                                                                            rolePermission.name ===
                                                                            permission.name,
                                                                    )}
                                                                    disabled={
                                                                        processing ||
                                                                        !can(
                                                                            'role:update',
                                                                        )
                                                                    }
                                                                    onCheckedChange={(
                                                                        checked,
                                                                    ) =>
                                                                        togglePermission(
                                                                            permission.name,
                                                                            checked ===
                                                                                true,
                                                                        )
                                                                    }
                                                                />
                                                                <Label
                                                                    htmlFor={`permission-${permission.id}`}
                                                                    className="font-normal"
                                                                >
                                                                    {
                                                                        permission.name
                                                                    }
                                                                </Label>
                                                            </div>
                                                        ),
                                                    )}
                                                </div>
                                            </div>
                                        ),
                                    )}
                                </div>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Nessun ruolo selezionato.
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    <Card className="order-first lg:order-last">
                        <CardHeader className="flex flex-row items-start justify-between gap-2">
                            <div className="space-y-1.5">
                                <CardTitle>Ruoli</CardTitle>
                                <CardDescription>
                                    Seleziona un ruolo per vederne i permessi
                                </CardDescription>
                            </div>
                            {can('role:create') && <CreateRoleDialog />}
                        </CardHeader>
                        <CardContent>
                            {roles.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Nessun ruolo presente.
                                </p>
                            ) : (
                                <div className="grid gap-2">
                                    {roles.map((role) => (
                                        <div
                                            key={role.id}
                                            className={cn(
                                                'flex items-center justify-between rounded-lg border px-3 py-2',
                                                selectedRoleId === role.id &&
                                                    'border-primary bg-muted',
                                            )}
                                        >
                                            <button
                                                type="button"
                                                className="flex flex-1 items-center gap-2 text-left"
                                                onClick={() =>
                                                    setSelectedRoleId(role.id)
                                                }
                                                data-test={`select-role-${role.id}`}
                                            >
                                                <span className="font-medium">
                                                    {role.name}
                                                </span>
                                                <Badge variant="secondary">
                                                    {role.permissions.length}{' '}
                                                    permessi
                                                </Badge>
                                            </button>
                                            <div className="flex items-center">
                                                {can('role:update') && (
                                                    <RenameRoleDialog
                                                        role={role}
                                                    />
                                                )}
                                                {can('role:delete') && (
                                                    <DeleteRoleDialog
                                                        role={role}
                                                    />
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

Roles.layout = {
    breadcrumbs: [
        {
            title: 'Ruoli',
            href: rolesIndex(),
        },
    ],
};
