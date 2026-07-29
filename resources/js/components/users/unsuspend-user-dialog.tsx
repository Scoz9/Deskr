import { Form } from '@inertiajs/react';
import UserController from '@/actions/App/Http/Controllers/UserController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import type { ManagedUser } from '@/types';

type UnsuspendUserDialogProps = {
    user: ManagedUser | null;
    onClose: () => void;
};

export default function UnsuspendUserDialog({
    user,
    onClose,
}: UnsuspendUserDialogProps) {
    return (
        <Dialog
            open={user !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent>
                {user && (
                    <>
                        <DialogTitle>Riattiva utente</DialogTitle>
                        <DialogDescription>
                            La sospensione di "{user.name}" verrà revocata e
                            l'utente potrà accedere di nuovo.
                        </DialogDescription>

                        <Form
                            key={user.id}
                            {...UserController.unsuspend.form({
                                user: user.id,
                            })}
                            options={{ preserveScroll: true }}
                            onSuccess={onClose}
                        >
                            {({ processing }) => (
                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button variant="secondary">
                                            Annulla
                                        </Button>
                                    </DialogClose>

                                    <Button disabled={processing} asChild>
                                        <button
                                            type="submit"
                                            data-test="confirm-unsuspend-user-button"
                                        >
                                            Riattiva utente
                                        </button>
                                    </Button>
                                </DialogFooter>
                            )}
                        </Form>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
