import { Form } from '@inertiajs/react';
import { Plus } from 'lucide-react';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function CreateRoleDialog() {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" data-test="create-role-button">
                    <Plus />
                    Nuovo ruolo
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Crea un nuovo ruolo</DialogTitle>
                <DialogDescription>
                    Dopo la creazione potrai associare i permessi al ruolo.
                </DialogDescription>

                <Form
                    {...RoleController.store.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    onSuccess={() => setOpen(false)}
                    className="space-y-6"
                >
                    {({ resetAndClearErrors, processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="create-role-name">Nome</Label>
                                <Input
                                    id="create-role-name"
                                    name="name"
                                    placeholder="Nome del ruolo"
                                    autoFocus
                                />
                                <InputError message={errors.name} />
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
                                        data-test="confirm-create-role-button"
                                    >
                                        Crea ruolo
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
