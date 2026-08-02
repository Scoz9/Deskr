import { Form } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import OrganizationController from '@/actions/App/Http/Controllers/OrganizationController';
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
import type { Organization } from '@/types';

export default function DeleteOrganizationDialog({
    organization,
}: {
    organization: Organization;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={`Elimina ${organization.name}`}
                    data-test={`delete-organization-button-${organization.id}`}
                >
                    <Trash2 className="text-destructive" />
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>
                    Vuoi eliminare l'organizzazione "{organization.name}"?
                </DialogTitle>
                <DialogDescription>
                    L'operazione non è reversibile ed è possibile solo se
                    l'organizzazione non ha più utenti collegati.
                </DialogDescription>

                <Form
                    {...OrganizationController.destroy.form({
                        organization: organization.id,
                    })}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="space-y-6"
                >
                    {({ resetAndClearErrors, processing, errors }) => (
                        <>
                            <InputError message={errors.organization} />

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
                                        data-test={`confirm-delete-organization-button-${organization.id}`}
                                    >
                                        Elimina organizzazione
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
