import { router } from '@inertiajs/react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import TicketActions from '@/components/tickets/ticket-actions';

vi.mock('@inertiajs/react', () => ({
    router: { visit: vi.fn() },
}));

beforeEach(() => {
    vi.mocked(router.visit).mockClear();
});

describe('the actions of the ticket detail page', () => {
    it('claims the ticket for whoever clicks "Assegna a me"', async () => {
        const user = userEvent.setup();

        render(
            <TicketActions
                ticketId={7}
                priority="normale"
                nextStatuses={['in_lavorazione']}
            />,
        );

        await user.click(screen.getByRole('button', { name: 'Assegna a me' }));

        expect(router.visit).toHaveBeenCalledWith(
            '/tickets/7/assign-to-me',
            expect.objectContaining({ method: 'post' }),
        );
    });

    it('offers no status picker when the lifecycle admits no passage', () => {
        render(
            <TicketActions ticketId={7} priority="normale" nextStatuses={[]} />,
        );

        expect(screen.queryByLabelText('Cambia stato')).not.toBeInTheDocument();
    });

    /*
     * `annullato` is a passage like any other in the lifecycle table, but
     * the console pulls it out of the status picker into its own button —
     * so it must never also appear as a choice inside the picker.
     */
    it('offers a dedicated cancel button instead of "annullato" in the picker', () => {
        render(
            <TicketActions
                ticketId={7}
                priority="normale"
                nextStatuses={['in_lavorazione', 'annullato']}
            />,
        );

        expect(
            screen.getByRole('button', { name: 'Annulla ticket' }),
        ).toBeInTheDocument();
        expect(screen.queryByText('Annullato')).not.toBeInTheDocument();
    });

    it('cancels the ticket through the same status endpoint', async () => {
        const user = userEvent.setup();

        render(
            <TicketActions
                ticketId={7}
                priority="normale"
                nextStatuses={['annullato']}
            />,
        );

        await user.click(
            screen.getByRole('button', { name: 'Annulla ticket' }),
        );

        expect(router.visit).toHaveBeenCalledWith(
            '/tickets/7/status',
            expect.objectContaining({
                method: 'patch',
                data: { status: 'annullato' },
            }),
        );
    });

    it('offers no cancel button when the lifecycle no longer admits it', () => {
        render(
            <TicketActions
                ticketId={7}
                priority="normale"
                nextStatuses={['in_lavorazione']}
            />,
        );

        expect(
            screen.queryByRole('button', { name: 'Annulla ticket' }),
        ).not.toBeInTheDocument();
    });
});
