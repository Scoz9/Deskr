import { Form } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import RoleController from '@/actions/App/Http/Controllers/RoleController';
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
import type { Role } from '@/types';

export default function DeleteRoleDialog({ role }: { role: Role }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={`Elimina ${role.name}`}
                    data-test={`delete-role-button-${role.id}`}
                >
                    <Trash2 className="text-destructive" />
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>
                    Vuoi eliminare il ruolo "{role.name}"?
                </DialogTitle>
                <DialogDescription>
                    L'operazione non è reversibile ed è possibile solo se il
                    ruolo non è associato ad alcun utente.
                </DialogDescription>

                <Form
                    {...RoleController.destroy.form({ role: role.id })}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="space-y-6"
                >
                    {({ resetAndClearErrors, processing, errors }) => (
                        <>
                            <InputError message={errors.role} />

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button
                                        variant="secondary"
                                        onClick={() => resetAndClearErrors()}
                                    >
                                        Annulla
                                    </Button>
                                </DialogClose>

                                <Button
                                    variant="destructive"
                                    disabled={processing}
                                    asChild
                                >
                                    <button
                                        type="submit"
                                        data-test={`confirm-delete-role-button-${role.id}`}
                                    >
                                        Elimina ruolo
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
