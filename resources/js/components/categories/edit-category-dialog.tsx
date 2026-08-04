import { Form } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
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
import type { Category, RoutableTeam } from '@/types';

export default function EditCategoryDialog({
    category,
    teams,
}: {
    category: Category;
    teams: RoutableTeam[];
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={`Modifica ${category.name}`}
                    data-test={`edit-category-button-${category.id}`}
                >
                    <Pencil />
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Modifica categoria</DialogTitle>
                <DialogDescription>
                    Cambiare team decide dove finiranno i prossimi ticket:
                    quelli già aperti restano dove erano stati instradati.
                </DialogDescription>

                <Form
                    {...CategoryController.update.form({
                        category: category.id,
                    })}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="space-y-6"
                >
                    {({ resetAndClearErrors, processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label
                                    htmlFor={`edit-category-name-${category.id}`}
                                >
                                    Nome
                                </Label>
                                <Input
                                    id={`edit-category-name-${category.id}`}
                                    name="name"
                                    defaultValue={category.name}
                                    autoFocus
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label
                                    htmlFor={`edit-category-team-${category.id}`}
                                >
                                    Team
                                </Label>
                                <TeamSelect
                                    id={`edit-category-team-${category.id}`}
                                    teams={teams}
                                    defaultValue={category.team.id}
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
                                    <button type="submit">Salva</button>
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
