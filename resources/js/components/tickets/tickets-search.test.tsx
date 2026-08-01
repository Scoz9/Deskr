import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import TicketsSearch from '@/components/tickets/tickets-search';

describe('the console search box', () => {
    it('does not call back on every keystroke', async () => {
        const user = userEvent.setup();
        const onChange = vi.fn();

        render(<TicketsSearch value={null} onChange={onChange} />);

        await user.type(screen.getByLabelText('Cerca'), 'stamp');

        expect(onChange).not.toHaveBeenCalled();
    });

    it('calls back once typing settles', async () => {
        const user = userEvent.setup();
        const onChange = vi.fn();

        render(<TicketsSearch value={null} onChange={onChange} />);

        await user.type(screen.getByLabelText('Cerca'), 'stampante');

        await waitFor(() => expect(onChange).toHaveBeenCalledWith('stampante'));
        expect(onChange).toHaveBeenCalledTimes(1);
    });

    it('clears the search instead of sending an empty term', async () => {
        const user = userEvent.setup();
        const onChange = vi.fn();

        render(<TicketsSearch value="stampante" onChange={onChange} />);

        await user.clear(screen.getByLabelText('Cerca'));

        await waitFor(() => expect(onChange).toHaveBeenCalledWith(null));
    });

    /*
     * The browser's back button, or a filter reset elsewhere on the page,
     * changes the search from outside — the box has to show what the
     * server actually answered, not what somebody was still typing.
     */
    it('follows a search that changed from outside', () => {
        const { rerender } = render(
            <TicketsSearch value={null} onChange={vi.fn()} />,
        );

        rerender(<TicketsSearch value="stampante" onChange={vi.fn()} />);

        expect(screen.getByLabelText('Cerca')).toHaveValue('stampante');
    });
});
