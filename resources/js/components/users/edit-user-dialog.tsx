import { Form } from '@inertiajs/react';
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
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import RoleSelect from '@/components/users/role-select';
import type { AssignableRole, ManagedUser } from '@/types';

type EditUserDialogProps = {
    user: ManagedUser | null;
    roles: AssignableRole[];
    onClose: () => void;
};

export default function EditUserDialog({
    user,
    roles,
    onClose,
}: EditUserDialogProps) {
    return (
        <Dialog
            open={user !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent>
                {user && (
                    <>
                        <DialogTitle>Modifica utente</DialogTitle>
                        <DialogDescription>
                            Aggiorna i dati di "{user.name}". Lascia vuota la
                            password per non modificarla.
                        </DialogDescription>

                        <Form
                            key={user.id}
                            {...UserController.update.form({ user: user.id })}
                            options={{ preserveScroll: true }}
                            onSuccess={onClose}
                            className="space-y-6"
                        >
                            {({ resetAndClearErrors, processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="edit-user-name">
                                            Nome
                                        </Label>
                                        <Input
                                            id="edit-user-name"
                                            name="name"
                                            defaultValue={user.name}
                                            autoFocus
                                        />
                                        <InputError message={errors.name} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="edit-user-email">
                                            Email
                                        </Label>
                                        <Input
                                            id="edit-user-email"
                                            name="email"
                                            type="email"
                                            defaultValue={user.email}
                                        />
                                        <InputError message={errors.email} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="edit-user-password">
                                            Nuova password (opzionale)
                                        </Label>
                                        <Input
                                            id="edit-user-password"
                                            name="password"
                                            type="password"
                                        />
                                        <InputError message={errors.password} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="edit-user-role">
                                            Ruolo
                                        </Label>
                                        <RoleSelect
                                            id="edit-user-role"
                                            roles={roles}
                                            defaultValue={user.role?.name}
                                        />
                                        <InputError message={errors.role} />
                                    </div>

                                    <DialogFooter className="gap-2">
                                        <DialogClose asChild>
                                            <Button
                                                variant="secondary"
                                                onClick={() =>
                                                    resetAndClearErrors()
                                                }
                                            >
                                                Annulla
                                            </Button>
                                        </DialogClose>

                                        <Button disabled={processing} asChild>
                                            <button
                                                type="submit"
                                                data-test="confirm-edit-user-button"
                                            >
                                                Salva
                                            </button>
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
