import { Form } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import UserController from '@/actions/App/Http/Controllers/UserController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import OrganizationSelect from '@/components/users/organization-select';
import RoleSelect from '@/components/users/role-select';
import type { AssignableRole, Organization } from '@/types';

export default function CreateUserDialog({
    roles,
    organizations,
}: {
    roles: AssignableRole[];
    organizations: Organization[];
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" data-test="create-user-button">
                    <Plus />
                    Nuovo utente
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Crea un nuovo utente</DialogTitle>
                <DialogDescription>
                    L'utente riceverà un'email all'indirizzo indicato con il
                    link per impostare la propria password.
                </DialogDescription>

                <Form
                    {...UserController.store.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    onSuccess={() => setOpen(false)}
                    className="space-y-6"
                >
                    {({ resetAndClearErrors, processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="create-user-name">Nome</Label>
                                <Input
                                    id="create-user-name"
                                    name="name"
                                    placeholder="Nome dell'utente"
                                    autoFocus
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="create-user-email">Email</Label>
                                <Input
                                    id="create-user-email"
                                    name="email"
                                    type="email"
                                    placeholder="email@esempio.it"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="create-user-role">Ruolo</Label>
                                <RoleSelect
                                    id="create-user-role"
                                    roles={roles}
                                />
                                <InputError message={errors.role} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="create-user-organization">
                                    Organizzazione
                                </Label>
                                <OrganizationSelect
                                    id="create-user-organization"
                                    organizations={organizations}
                                />
                                <InputError message={errors.organization_id} />
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button
                                        variant="secondary"
                                        onClick={() => resetAndClearErrors()}
                                    >
                                        Annulla
                                    </Button>
                                </DialogClose>

                                <Button disabled={processing} asChild>
                                    <button
                                        type="submit"
                                        data-test="confirm-create-user-button"
                                    >
                                        Crea utente
                                    </button>
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
