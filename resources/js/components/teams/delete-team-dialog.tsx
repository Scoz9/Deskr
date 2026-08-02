import { Form } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import TeamController from '@/actions/App/Http/Controllers/TeamController';
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
import type { Team } from '@/types';

export default function DeleteTeamDialog({ team }: { team: Team }) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={`Elimina ${team.name}`}
                    data-test={`delete-team-button-${team.id}`}
                >
                    <Trash2 className="text-destructive" />
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Vuoi eliminare il team "{team.name}"?</DialogTitle>
                <DialogDescription>
                    L'operazione non è reversibile ed è possibile solo se al
                    team non è instradata alcuna categoria e non ha ticket.
                </DialogDescription>

                <Form
                    {...TeamController.destroy.form({ team: team.id })}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="space-y-6"
                >
                    {({ resetAndClearErrors, processing, errors }) => (
                        <>
                            <InputError message={errors.team} />

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
                                        data-test={`confirm-delete-team-button-${team.id}`}
                                    >
                                        Elimina team
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
