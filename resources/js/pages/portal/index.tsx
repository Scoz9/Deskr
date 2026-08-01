import { Head, Link, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { leave } from '@/routes/portal';

/**
 * The seven statuses of §4, so that the label of each one is a key the
 * translation files are checked against.
 */
type TicketStatus =
    | 'nuovo'
    | 'assegnato'
    | 'in_lavorazione'
    | 'in_attesa'
    | 'risolto'
    | 'chiuso'
    | 'annullato';

type PortalTicket = {
    reference: string;
    subject: string;
    status: TicketStatus;
    openedAt: string | null;
    url: string;
};

type Props = {
    tickets: PortalTicket[];
};

/**
 * "My requests": everything this person has opened, and nothing of anybody
 * else's. Read only — replying from here is step 27.
 */
export default function PortalIndex({ tickets }: Props) {
    const { t } = useTranslation();

    const readableDate = (value: string | null): string =>
        value === null ? '' : new Date(value).toLocaleDateString();

    return (
        <>
            <Head title={t('portal.mine')} />

            <div className="flex min-h-svh flex-col items-center bg-background p-6 md:p-10">
                <div className="w-full max-w-2xl space-y-8">
                    <header className="flex items-start justify-between gap-4">
                        <h1 className="text-2xl font-medium">
                            {t('portal.mine')}
                        </h1>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => router.post(leave.url())}
                        >
                            {t('portal.leave')}
                        </Button>
                    </header>

                    {tickets.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {t('portal.empty')}
                        </p>
                    ) : (
                        <ul className="space-y-3">
                            {tickets.map((ticket) => (
                                <li
                                    key={ticket.reference}
                                    className="rounded-md border p-4"
                                >
                                    <Link
                                        href={ticket.url}
                                        className="font-medium underline underline-offset-4"
                                    >
                                        {ticket.subject}
                                    </Link>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        <span className="font-mono">
                                            {ticket.reference}
                                        </span>{' '}
                                        · {t(`ticket.status.${ticket.status}`)}
                                        {ticket.openedAt !== null && (
                                            <>
                                                {' '}
                                                ·{' '}
                                                {readableDate(ticket.openedAt)}
                                            </>
                                        )}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>
        </>
    );
}
