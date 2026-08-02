import { Form } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useState } from 'react';
import TeamController from '@/actions/App/Http/Controllers/TeamController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Team } from '@/types';

export default function RenameTeamDialog({ team }: { team: Team }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={`Rinomina ${team.name}`}
                    data-test={`rename-team-button-${team.id}`}
                >
                    <Pencil />
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Rinomina team</DialogTitle>

                <Form
                    {...TeamController.update.form({ team: team.id })}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="space-y-6"
                >
                    {({ resetAndClearErrors, processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor={`rename-team-name-${team.id}`}>
                                    Nome
                                </Label>
                                <Input
                                    id={`rename-team-name-${team.id}`}
                                    name="name"
                                    defaultValue={team.name}
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
