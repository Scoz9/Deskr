import { router } from '@inertiajs/react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import TicketReplyForm from '@/components/tickets/ticket-reply-form';

vi.mock('@inertiajs/react', () => ({
    router: { post: vi.fn() },
}));

const attachmentLimits = {
    maxFiles: 2,
    maxBytes: 1024,
    mimeTypes: ['image/png', 'application/pdf'],
};

function file(name: string, type: string, size = 10): File {
    return new File([new Uint8Array(size)], name, { type });
}

function renderForm() {
    return render(
        <TicketReplyForm ticketId={7} attachmentLimits={attachmentLimits} />,
    );
}

beforeEach(() => {
    vi.mocked(router.post).mockClear();
});

describe('the reply and internal note composer', () => {
    it('does not bother the server with an empty message', async () => {
        const user = userEvent.setup();
        renderForm();

        await user.click(
            screen.getByRole('button', { name: 'Invia risposta' }),
        );

        expect(router.post).not.toHaveBeenCalled();
        expect(screen.getByText('Scrivi un messaggio.')).toBeInTheDocument();
    });

    it('sends a public reply by default', async () => {
        const user = userEvent.setup();
        renderForm();

        await user.type(
            screen.getByLabelText('Rispondi'),
            'Abbiamo controllato il log.',
        );
        await user.click(
            screen.getByRole('button', { name: 'Invia risposta' }),
        );

        expect(router.post).toHaveBeenCalledTimes(1);

        const [url, payload] = vi.mocked(router.post).mock.calls[0];

        expect(url).toBe('/tickets/7/messages');
        expect(payload).toMatchObject({
            body: 'Abbiamo controllato il log.',
            is_internal: false,
        });
    });

    /*
     * The checkbox is the only thing that tells a reply and a note apart —
     * `NewReply` reads it as one flag on the same fact, not as two forms.
     */
    it('sends an internal note once the checkbox is ticked', async () => {
        const user = userEvent.setup();
        renderForm();

        await user.type(
            screen.getByLabelText('Rispondi'),
            'Da verificare con il fornitore.',
        );
        await user.click(
            screen.getByLabelText('Nota interna — il richiedente non la vedrà'),
        );
        await user.click(
            screen.getByRole('button', { name: 'Aggiungi nota interna' }),
        );

        const [, payload] = vi.mocked(router.post).mock.calls[0];

        expect(payload).toMatchObject({ is_internal: true });
    });

    it('takes the files picked along with the message', async () => {
        const user = userEvent.setup();
        renderForm();

        await user.type(
            screen.getByLabelText('Rispondi'),
            'Ecco lo screenshot.',
        );
        await user.upload(
            screen.getByLabelText('Allegati'),
            file('errore.png', 'image/png'),
        );
        await user.click(
            screen.getByRole('button', { name: 'Invia risposta' }),
        );

        const [, payload] = vi.mocked(router.post).mock.calls[0];
        const sent = (payload as { attachments: File[] }).attachments;

        expect(sent).toHaveLength(1);
        expect(sent[0].name).toBe('errore.png');
    });

    it('does not upload a file the whitelist already excludes', async () => {
        const user = userEvent.setup({ applyAccept: false });
        renderForm();

        await user.type(screen.getByLabelText('Rispondi'), 'Ecco lo script.');
        await user.upload(
            screen.getByLabelText('Allegati'),
            file('script.php', 'application/x-php'),
        );
        await user.click(
            screen.getByRole('button', { name: 'Invia risposta' }),
        );

        expect(
            screen.getByText(
                'Uno dei file scelti non è tra i formati ammessi.',
            ),
        ).toBeInTheDocument();
        expect(router.post).not.toHaveBeenCalled();
    });

    it('shows what the server refused about the message', async () => {
        const user = userEvent.setup();
        renderForm();

        vi.mocked(router.post).mockImplementation(
            (_url: unknown, _payload: unknown, options?: unknown) => {
                (
                    options as {
                        onError?: (errors: Record<string, string>) => void;
                    }
                )?.onError?.({
                    body: 'Il messaggio è troppo lungo.',
                });
            },
        );

        await user.type(screen.getByLabelText('Rispondi'), 'Un messaggio.');
        await user.click(
            screen.getByRole('button', { name: 'Invia risposta' }),
        );

        expect(
            await screen.findByText('Il messaggio è troppo lungo.'),
        ).toBeInTheDocument();
    });

    it('clears the form once the message is sent', async () => {
        const user = userEvent.setup();

        vi.mocked(router.post).mockImplementation(
            (_url: unknown, _payload: unknown, options?: unknown) => {
                (
                    options as {
                        onSuccess?: () => void;
                    }
                )?.onSuccess?.();
            },
        );

        renderForm();

        await user.type(screen.getByLabelText('Rispondi'), 'Un messaggio.');
        await user.click(
            screen.getByRole('button', { name: 'Invia risposta' }),
        );

        expect(screen.getByLabelText('Rispondi')).toHaveValue('');
    });
});
