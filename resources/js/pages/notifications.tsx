import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import PlaceholderPalette from '@/components/notifications/placeholder-palette';
import TemplatePreview from '@/components/notifications/template-preview';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { useCan } from '@/hooks/use-can';
import { notificationKit } from '@/lib/notification-kit/api';
import type {
    Preview,
    Template,
    TemplateFilters,
    TemplateVersion,
} from '@/lib/notification-kit/types';
import { outbox as outboxRoute } from '@/routes/notifications';
import { index as notificationsIndex } from '@/routes/notifications';

export default function Notifications() {
    const can = useCan();

    const [filters, setFilters] = useState<TemplateFilters>({});
    const [templates, setTemplates] = useState<Template[]>([]);
    const [selectedKey, setSelectedKey] = useState<string | null>(null);
    const [selected, setSelected] = useState<Template | null>(null);
    const [versions, setVersions] = useState<TemplateVersion[]>([]);
    const [preview, setPreview] = useState<Preview | null>(null);

    const [subject, setSubject] = useState('');
    const [body, setBody] = useState('');
    const [requiresConfirmation, setRequiresConfirmation] = useState(false);

    const [loading, setLoading] = useState(true);
    const [processing, setProcessing] = useState(false);

    // Bumped from event handlers to pull the list again after a mutation.
    const [refreshToken, setRefreshToken] = useState(0);

    useEffect(() => {
        let cancelled = false;

        notificationKit.templates
            .list(filters)
            .then((response) => {
                if (!cancelled) {
                    setTemplates(response.data);
                    setLoading(false);
                }
            })
            .catch((error: unknown) => {
                if (!cancelled) {
                    setLoading(false);
                    toast.error(
                        error instanceof Error
                            ? error.message
                            : 'Caricamento non riuscito.',
                    );
                }
            });

        return () => {
            cancelled = true;
        };
    }, [filters, refreshToken]);

    useEffect(() => {
        if (selectedKey === null) {
            return;
        }

        void (async () => {
            const [template, history] = await Promise.all([
                notificationKit.templates.show(selectedKey),
                notificationKit.templates.versions(selectedKey),
            ]);

            setSelected(template.data);
            setVersions(history.data);
            setSubject(
                template.data.subject ?? template.data.default_subject ?? '',
            );
            setBody(template.data.body ?? template.data.default_body);
            setRequiresConfirmation(template.data.requires_confirmation);
        })();
    }, [selectedKey]);

    // Debounced preview of whatever is currently in the editor.
    useEffect(() => {
        if (selectedKey === null) {
            return;
        }

        const timer = window.setTimeout(() => {
            void notificationKit.templates
                .preview(selectedKey, { subject, body })
                .then((response) => setPreview(response.data))
                .catch(() => setPreview(null));
        }, 400);

        return () => window.clearTimeout(timer);
    }, [selectedKey, subject, body]);

    const save = async () => {
        if (selected === null) {
            return;
        }

        setProcessing(true);

        try {
            await notificationKit.templates.updateContent(selected.key, {
                subject: subject === '' ? null : subject,
                body: body === '' ? null : body,
                requires_confirmation: requiresConfirmation,
            });

            toast.success('Contenuto salvato.');
            setRefreshToken((token) => token + 1);

            const history = await notificationKit.templates.versions(
                selected.key,
            );
            setVersions(history.data);
        } catch (error) {
            toast.error(
                error instanceof Error
                    ? error.message
                    : 'Salvataggio non riuscito.',
            );
        } finally {
            setProcessing(false);
        }
    };

    const toggleArchive = async () => {
        if (selected === null) {
            return;
        }

        setProcessing(true);

        try {
            const archived = selected.archived_at !== null;

            const response = archived
                ? await notificationKit.templates.unarchive(selected.key)
                : await notificationKit.templates.archive(selected.key);

            setSelected(response.data);
            toast.success(
                archived ? 'Contenuto ripristinato.' : 'Contenuto archiviato.',
            );
            setRefreshToken((token) => token + 1);
        } catch (error) {
            toast.error(
                error instanceof Error
                    ? error.message
                    : 'Operazione non riuscita.',
            );
        } finally {
            setProcessing(false);
        }
    };

    const resetToDefault = () => {
        if (selected === null) {
            return;
        }

        setSubject(selected.default_subject ?? '');
        setBody(selected.default_body);
    };

    return (
        <>
            <Head title="Notifiche" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div className="flex flex-wrap items-end gap-3">
                        <div className="grid gap-1.5">
                            <Label htmlFor="search">Cerca</Label>
                            <Input
                                id="search"
                                type="search"
                                placeholder="Chiave o nome"
                                className="w-56"
                                value={filters.search ?? ''}
                                onChange={(event) =>
                                    setFilters({
                                        ...filters,
                                        search: event.target.value || undefined,
                                    })
                                }
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="type">Tipo</Label>
                            <Select
                                value={filters.type ?? 'all'}
                                onValueChange={(value) =>
                                    setFilters({
                                        ...filters,
                                        type:
                                            value === 'all'
                                                ? undefined
                                                : (value as TemplateFilters['type']),
                                    })
                                }
                            >
                                <SelectTrigger id="type" className="w-40">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Tutti</SelectItem>
                                    <SelectItem value="email">Email</SelectItem>
                                    <SelectItem value="notification">
                                        Notifiche
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="archived">Archiviati</Label>
                            <Select
                                value={filters.archived ?? 'none'}
                                onValueChange={(value) =>
                                    setFilters({
                                        ...filters,
                                        archived:
                                            value === 'none'
                                                ? undefined
                                                : (value as TemplateFilters['archived']),
                                    })
                                }
                            >
                                <SelectTrigger id="archived" className="w-44">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">
                                        Solo attivi
                                    </SelectItem>
                                    <SelectItem value="with">
                                        Inclusi archiviati
                                    </SelectItem>
                                    <SelectItem value="only">
                                        Solo archiviati
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    {can('notification:approve') && (
                        <Button variant="outline" asChild>
                            <Link href={outboxRoute()}>Coda invii</Link>
                        </Button>
                    )}
                </div>

                <div className="grid gap-4 lg:grid-cols-[1fr_2fr]">
                    <Card>
                        <CardHeader>
                            <CardTitle>Contenuti</CardTitle>
                            <CardDescription>
                                Email e notifiche configurate nel sistema.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {loading ? (
                                <Spinner />
                            ) : templates.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Nessun contenuto trovato.
                                </p>
                            ) : (
                                <ul className="flex flex-col gap-1">
                                    {templates.map((template) => (
                                        <li key={template.key}>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setSelectedKey(template.key)
                                                }
                                                className={`w-full rounded-md px-3 py-2 text-left hover:bg-accent ${
                                                    selectedKey === template.key
                                                        ? 'bg-accent'
                                                        : ''
                                                }`}
                                            >
                                                <span className="flex items-center gap-2">
                                                    <span className="font-medium">
                                                        {template.name}
                                                    </span>
                                                    <Badge variant="outline">
                                                        {template.type ===
                                                        'email'
                                                            ? 'Email'
                                                            : 'Notifica'}
                                                    </Badge>
                                                    {template.requires_confirmation && (
                                                        <Badge>Conferma</Badge>
                                                    )}
                                                    {template.archived_at !==
                                                        null && (
                                                        <Badge variant="secondary">
                                                            Archiviato
                                                        </Badge>
                                                    )}
                                                </span>
                                                <span className="block text-xs text-muted-foreground">
                                                    {template.key}
                                                </span>
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>
                                {selected
                                    ? selected.name
                                    : 'Modifica contenuto'}
                            </CardTitle>
                            <CardDescription>
                                {selected
                                    ? (selected.description ?? selected.key)
                                    : 'Seleziona un contenuto per modificarlo.'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {selected === null ? (
                                <p className="text-sm text-muted-foreground">
                                    Nessun contenuto selezionato.
                                </p>
                            ) : (
                                <div className="grid gap-6 xl:grid-cols-[2fr_1fr]">
                                    <div className="flex flex-col gap-4">
                                        {selected.type === 'email' && (
                                            <div className="grid gap-1.5">
                                                <Label htmlFor="subject">
                                                    Oggetto
                                                </Label>
                                                <Input
                                                    id="subject"
                                                    value={subject}
                                                    disabled={
                                                        !can(
                                                            'notification:update',
                                                        )
                                                    }
                                                    onChange={(event) =>
                                                        setSubject(
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </div>
                                        )}

                                        <div className="grid gap-1.5">
                                            <Label htmlFor="body">
                                                Contenuto (Markdown)
                                            </Label>
                                            <textarea
                                                id="body"
                                                rows={14}
                                                value={body}
                                                disabled={
                                                    !can('notification:update')
                                                }
                                                onChange={(event) =>
                                                    setBody(event.target.value)
                                                }
                                                className="w-full rounded-md border bg-transparent px-3 py-2 font-mono text-sm shadow-xs disabled:opacity-50"
                                            />
                                        </div>

                                        {selected.supports_confirmation && (
                                            <label className="flex items-center gap-2 text-sm">
                                                <input
                                                    type="checkbox"
                                                    checked={
                                                        requiresConfirmation
                                                    }
                                                    disabled={
                                                        !can(
                                                            'notification:update',
                                                        )
                                                    }
                                                    onChange={(event) =>
                                                        setRequiresConfirmation(
                                                            event.target
                                                                .checked,
                                                        )
                                                    }
                                                />
                                                Richiedi conferma manuale prima
                                                dell&apos;invio
                                            </label>
                                        )}

                                        {preview !== null &&
                                            preview.missing_placeholders
                                                .length > 0 && (
                                                <p className="text-sm text-muted-foreground">
                                                    Segnaposto senza valore
                                                    d&apos;esempio:{' '}
                                                    {preview.missing_placeholders.join(
                                                        ', ',
                                                    )}
                                                </p>
                                            )}

                                        <div className="flex flex-wrap gap-2">
                                            {can('notification:update') && (
                                                <>
                                                    <Button
                                                        type="button"
                                                        disabled={processing}
                                                        onClick={() =>
                                                            void save()
                                                        }
                                                    >
                                                        Salva
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        onClick={resetToDefault}
                                                    >
                                                        Ripristina default
                                                    </Button>
                                                </>
                                            )}
                                            {can('notification:archive') && (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    disabled={processing}
                                                    onClick={() =>
                                                        void toggleArchive()
                                                    }
                                                >
                                                    {selected.archived_at !==
                                                    null
                                                        ? 'Ripristina'
                                                        : 'Archivia'}
                                                </Button>
                                            )}
                                        </div>

                                        {preview !== null && (
                                            <div className="grid gap-1.5">
                                                <Label>Anteprima</Label>
                                                <TemplatePreview
                                                    html={preview.body_html}
                                                    className="h-72"
                                                />
                                            </div>
                                        )}
                                    </div>

                                    <div className="flex flex-col gap-6">
                                        <div className="grid gap-2">
                                            <Label>Segnaposto</Label>
                                            <PlaceholderPalette
                                                placeholders={
                                                    selected.placeholders
                                                }
                                                onInsert={(token) =>
                                                    setBody(
                                                        (current) =>
                                                            `${current}${token}`,
                                                    )
                                                }
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label>Storico modifiche</Label>
                                            {versions.length === 0 ? (
                                                <p className="text-sm text-muted-foreground">
                                                    Nessuna modifica registrata.
                                                </p>
                                            ) : (
                                                <ul className="flex flex-col gap-2">
                                                    {versions.map((version) => (
                                                        <li
                                                            key={version.id}
                                                            className="rounded-md border px-3 py-2 text-sm"
                                                        >
                                                            <span className="block font-medium">
                                                                {version.subject ??
                                                                    '(default)'}
                                                            </span>
                                                            <span className="text-xs text-muted-foreground">
                                                                {version.edited_by ??
                                                                    'Sistema'}
                                                            </span>
                                                        </li>
                                                    ))}
                                                </ul>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

Notifications.layout = {
    breadcrumbs: [
        {
            title: 'Notifiche',
            href: notificationsIndex(),
        },
    ],
};
