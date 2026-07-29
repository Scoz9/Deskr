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
import type { ManagedUser } from '@/types';

type SuspendUserDialogProps = {
    user: ManagedUser | null;
    onClose: () => void;
};

export default function SuspendUserDialog({
    user,
    onClose,
}: SuspendUserDialogProps) {
    return (
        <Dialog
            open={user !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent>
                {user && (
                    <>
                        <DialogTitle>Sospendi utente</DialogTitle>
                        <DialogDescription>
                            "{user.name}" non potrà più accedere finché la
                            sospensione non verrà revocata. Lascia vuota la data
                            per una sospensione permanente.
                        </DialogDescription>

                        <Form
                            key={user.id}
                            {...UserController.suspend.form({ user: user.id })}
                            options={{ preserveScroll: true }}
                            onSuccess={onClose}
                            className="space-y-6"
                        >
                            {({ resetAndClearErrors, processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="suspend-user-until">
                                            Sospendi fino al
                                        </Label>
                                        <Input
                                            id="suspend-user-until"
                                            name="suspended_until"
                                            type="datetime-local"
                                        />
                                        <InputError
                                            message={errors.suspended_until}
                                        />
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

                                        <Button
                                            variant="destructive"
                                            disabled={processing}
                                            asChild
                                        >
                                            <button
                                                type="submit"
                                                data-test="confirm-suspend-user-button"
                                            >
                                                Sospendi utente
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
