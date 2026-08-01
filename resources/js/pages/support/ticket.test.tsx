import { router } from '@inertiajs/react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import SupportTicket from '@/pages/support/ticket';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    router: { post: vi.fn() },
}));

vi.mock('react-i18next', () => ({
    useTranslation: () => ({ t: (key: string) => key }),
}));

const ticket = {
    reference: 'DSK-000123',
    subject: 'La stampante non risponde',
    status: 'in_attesa' as const,
    openedAt: '2026-07-30T10:00:00Z',
    messages: [],
    replyUrl: '/assistenza/ticket/123/rispondi',
};

function renderPage(canReply: boolean) {
    return render(<SupportTicket ticket={ticket} canReply={canReply} />);
}

beforeEach(() => {
    vi.mocked(router.post).mockClear();
});

describe('the ticket as whoever asked sees it', () => {
    it('offers no way to reply without a portal session', () => {
        renderPage(false);

        expect(
            screen.queryByLabelText('ticket.reply.label'),
        ).not.toBeInTheDocument();
        expect(screen.getByText('ticket.readOnly')).toBeInTheDocument();
    });

    it('offers the reply form to a portal session', () => {
        renderPage(true);

        expect(screen.getByLabelText('ticket.reply.label')).toBeInTheDocument();
        expect(screen.queryByText('ticket.readOnly')).toBeNull();
    });

    it('does not bother the server with an empty reply', async () => {
        const user = userEvent.setup();
        renderPage(true);

        await user.click(
            screen.getByRole('button', { name: 'ticket.reply.submit' }),
        );

        expect(
            await screen.findByText('support.errors.required'),
        ).toBeInTheDocument();
        expect(router.post).not.toHaveBeenCalled();
    });

    it('sends the reply to the url the server carried', async () => {
        const user = userEvent.setup();
        renderPage(true);

        await user.type(
            screen.getByLabelText('ticket.reply.label'),
            'Succede ancora stamattina.',
        );
        await user.click(
            screen.getByRole('button', { name: 'ticket.reply.submit' }),
        );

        expect(router.post).toHaveBeenCalledTimes(1);

        const [url, payload] = vi.mocked(router.post).mock.calls[0];

        expect(url).toBe(ticket.replyUrl);
        expect(payload).toMatchObject({ body: 'Succede ancora stamattina.' });
    });

    it('shows what the server refused', async () => {
        const user = userEvent.setup();
        renderPage(true);

        vi.mocked(router.post).mockImplementation(
            (_url: unknown, _payload: unknown, options?: unknown) => {
                (
                    options as {
                        onError?: (errors: Record<string, string>) => void;
                    }
                )?.onError?.({ body: 'La risposta è troppo lunga.' });
            },
        );

        await user.type(
            screen.getByLabelText('ticket.reply.label'),
            'Succede ancora stamattina.',
        );
        await user.click(
            screen.getByRole('button', { name: 'ticket.reply.submit' }),
        );

        expect(
            await screen.findByText('La risposta è troppo lunga.'),
        ).toBeInTheDocument();
    });
});
