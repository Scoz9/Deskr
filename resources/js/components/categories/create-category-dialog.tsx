import { Form } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import CategoryController from '@/actions/App/Http/Controllers/CategoryController';
import TeamSelect from '@/components/categories/team-select';
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
import type { RoutableTeam } from '@/types';

export default function CreateCategoryDialog({
    teams,
}: {
    teams: RoutableTeam[];
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm" data-test="create-category-button">
                    <Plus />
                    Nuova categoria
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Crea una nuova categoria</DialogTitle>
                <DialogDescription>
                    La categoria è quello che il modulo pubblico mostra a chi
                    chiede aiuto, e decide su quale team finiscono i ticket.
                </DialogDescription>

                <Form
                    {...CategoryController.store.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    onSuccess={() => setOpen(false)}
                    className="space-y-6"
                >
                    {({ resetAndClearErrors, processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="create-category-name">
                                    Nome
                                </Label>
                                <Input
                                    id="create-category-name"
                                    name="name"
                                    placeholder="Nome della categoria"
                                    autoFocus
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="create-category-team">
                                    Team
                                </Label>
                                <TeamSelect
                                    id="create-category-team"
                                    teams={teams}
                                />
                                <InputError message={errors.team_id} />
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
                                        data-test="confirm-create-category-button"
                                    >
                                        Crea categoria
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
