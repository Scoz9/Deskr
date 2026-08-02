import { Head } from '@inertiajs/react';
import CreateOrganizationDialog from '@/components/organizations/create-organization-dialog';
import DeleteOrganizationDialog from '@/components/organizations/delete-organization-dialog';
import RenameOrganizationDialog from '@/components/organizations/rename-organization-dialog';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useCan } from '@/hooks/use-can';
import { index as organizationsIndex } from '@/routes/organizations';
import type { Organization } from '@/types';

type OrganizationsProps = {
    organizations: Organization[];
};

export default function Organizations({ organizations }: OrganizationsProps) {
    const can = useCan();

    return (
        <>
            <Head title="Organizzazioni" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Card>
                    <CardHeader className="flex flex-row items-start justify-between gap-2">
                        <div className="space-y-1.5">
                            <CardTitle>Organizzazioni</CardTitle>
                            <CardDescription>
                                Le aziende per conto delle quali i richiedenti
                                scrivono
                            </CardDescription>
                        </div>
                        {can('organization:create') && (
                            <CreateOrganizationDialog />
                        )}
                    </CardHeader>
                    <CardContent>
                        {organizations.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Nessuna organizzazione presente.
                            </p>
                        ) : (
                            <div className="grid gap-2">
                                {organizations.map((organization) => (
                                    <div
                                        key={organization.id}
                                        className="flex items-center justify-between rounded-lg border px-3 py-2"
                                    >
                                        <div className="flex items-center gap-2">
                                            <span className="font-medium">
                                                {organization.name}
                                            </span>
                                            <Badge variant="secondary">
                                                {organization.users_count}{' '}
                                                utenti
                                            </Badge>
                                        </div>
                                        <div className="flex items-center">
                                            {can('organization:update') && (
                                                <RenameOrganizationDialog
                                                    organization={organization}
                                                />
                                            )}
                                            {can('organization:delete') && (
                                                <DeleteOrganizationDialog
                                                    organization={organization}
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
        </>
    );
}

Organizations.layout = {
    breadcrumbs: [
        {
            title: 'Organizzazioni',
            href: organizationsIndex(),
        },
    ],
};
