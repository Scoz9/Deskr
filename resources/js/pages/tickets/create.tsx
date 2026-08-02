import { Head, router, setLayoutProps } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { priorityLabels } from '@/components/tickets/tickets-table';
import type { TicketPriority } from '@/components/tickets/tickets-table';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { index as ticketsIndex, store as ticketsStore } from '@/routes/tickets';

type Category = {
    id: number;
    name: string;
};

type Props = {
    categories: Category[];
};

const DEFAULT_PRIORITY: TicketPriority = 'normale';

type Values = {
    name: string;
    email: string;
    categoryId: string;
    priority: TicketPriority;
    subject: string;
    body: string;
};

const EMPTY_VALUES: Values = {
    name: '',
    email: '',
    categoryId: '',
    priority: DEFAULT_PRIORITY,
    subject: '',
    body: '',
};

/**
 * The console's own door into the intake (roadmap step 39): an operator
 * opens this on behalf of whoever called or walked in, on `channel =
 * telefono` regardless of which of the two it was — nobody types a request
 * in front of them any differently than they would over the phone.
 *
 * Unlike the public form, an operator sets the priority directly: `§3`
 * keeps it from the public intake because "if the requester chooses,
 * everything is urgent", a reason that does not apply to the person taking
 * the call.
 */
export default function TicketsCreate({ categories }: Props) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Ticket', href: ticketsIndex() },
            { title: 'Nuovo', href: ticketsStore.url() },
        ],
    });

    const [values, setValues] = useState<Values>(EMPTY_VALUES);
    const [refusals, setRefusals] = useState<Record<string, string>>({});
    const [sending, setSending] = useState(false);

    const update = <Field extends keyof Values>(
        field: Field,
        value: Values[Field],
    ): void => {
        setValues((current) => ({ ...current, [field]: value }));
    };

    const submit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        setSending(true);

        router.post(
            ticketsStore.url(),
            {
                name: values.name,
                email: values.email,
                category_id: values.categoryId,
                priority: values.priority,
                subject: values.subject,
                body: values.body,
            },
            {
                onError: (serverErrors) => setRefusals(serverErrors),
                onFinish: () => setSending(false),
            },
        );
    };

    return (
        <>
            <Head title="Nuovo ticket" />

            <div className="mx-auto flex w-full max-w-xl flex-1 flex-col gap-6 p-4">
                <h1 className="text-2xl font-medium">Nuovo ticket</h1>

                <form onSubmit={submit} className="grid gap-6" noValidate>
                    <div className="grid gap-2">
                        <Label htmlFor="create-name">Richiedente</Label>
                        <Input
                            id="create-name"
                            name="name"
                            autoComplete="name"
                            aria-invalid={refusals.name !== undefined}
                            value={values.name}
                            onChange={(event) =>
                                update('name', event.target.value)
                            }
                        />
                        <InputError message={refusals.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="create-email">Email</Label>
                        <Input
                            id="create-email"
                            name="email"
                            type="email"
                            autoComplete="email"
                            aria-invalid={refusals.email !== undefined}
                            value={values.email}
                            onChange={(event) =>
                                update('email', event.target.value)
                            }
                        />
                        <InputError message={refusals.email} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="create-category">Categoria</Label>
                        <Select
                            value={values.categoryId}
                            onValueChange={(value) =>
                                update('categoryId', value)
                            }
                        >
                            <SelectTrigger id="create-category">
                                <SelectValue placeholder="Scegli…" />
                            </SelectTrigger>
                            <SelectContent>
                                {categories.map((category) => (
                                    <SelectItem
                                        key={category.id}
                                        value={category.id.toString()}
                                    >
                                        {category.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={refusals.category_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="create-priority">Priorità</Label>
                        <Select
                            value={values.priority}
                            onValueChange={(value) =>
                                update('priority', value as TicketPriority)
                            }
                        >
                            <SelectTrigger id="create-priority">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {Object.entries(priorityLabels).map(
                                    ([value, label]) => (
                                        <SelectItem key={value} value={value}>
                                            {label}
                                        </SelectItem>
                                    ),
                                )}
                            </SelectContent>
                        </Select>
                        <InputError message={refusals.priority} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="create-subject">Oggetto</Label>
                        <Input
                            id="create-subject"
                            name="subject"
                            aria-invalid={refusals.subject !== undefined}
                            value={values.subject}
                            onChange={(event) =>
                                update('subject', event.target.value)
                            }
                        />
                        <InputError message={refusals.subject} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="create-body">Descrizione</Label>
                        <Textarea
                            id="create-body"
                            name="body"
                            rows={6}
                            maxLength={5000}
                            aria-invalid={refusals.body !== undefined}
                            value={values.body}
                            onChange={(event) =>
                                update('body', event.target.value)
                            }
                        />
                        <InputError message={refusals.body} />
                    </div>

                    <Button
                        type="submit"
                        disabled={sending}
                        className="justify-self-start"
                    >
                        Crea ticket
                    </Button>
                </form>
            </div>
        </>
    );
}
