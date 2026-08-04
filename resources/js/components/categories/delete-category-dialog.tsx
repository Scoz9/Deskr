import { Form } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import CategoryController from '@/actions/App/Http/Controllers/CategoryController';
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
import type { Category } from '@/types';

export default function DeleteCategoryDialog({
    category,
}: {
    category: Category;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={`Elimina ${category.name}`}
                    data-test={`delete-category-button-${category.id}`}
                >
                    <Trash2 className="text-destructive" />
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>
                    Vuoi eliminare la categoria "{category.name}"?
                </DialogTitle>
                <DialogDescription>
                    L'operazione non è reversibile ed è possibile solo se nessun
                    ticket è stato aperto sotto questa categoria.
                </DialogDescription>

                <Form
                    {...CategoryController.destroy.form({
                        category: category.id,
                    })}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="space-y-6"
                >
                    {({ resetAndClearErrors, processing, errors }) => (
                        <>
                            <InputError message={errors.category} />

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
                                        data-test={`confirm-delete-category-button-${category.id}`}
                                    >
                                        Elimina categoria
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
