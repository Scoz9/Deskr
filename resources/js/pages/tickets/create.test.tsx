import { router } from '@inertiajs/react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import TicketsCreate from '@/pages/tickets/create';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    router: { post: vi.fn() },
    setLayoutProps: vi.fn(),
}));

const categories = [
    { id: 1, name: 'Accessi' },
    { id: 2, name: 'Rete' },
];

function renderForm() {
    return render(<TicketsCreate categories={categories} />);
}

beforeEach(() => {
    vi.mocked(router.post).mockClear();
});

async function fillInTheForm(): Promise<void> {
    const user = userEvent.setup();

    await user.type(screen.getByLabelText('Richiedente'), 'Mario Rossi');
    await user.type(screen.getByLabelText('Email'), 'mario.rossi@example.com');
    await user.click(screen.getByLabelText('Categoria'));
    await user.click(screen.getByRole('option', { name: 'Rete' }));
    await user.type(screen.getByLabelText('Oggetto'), 'Non riesco ad accedere');
    await user.type(
        screen.getByLabelText('Descrizione'),
        'Chiamata dal numero interno 204: password dimenticata.',
    );
}

describe('the ticket the console opens on behalf of a caller', () => {
    it('offers the categories the server sent, in the order it sent them', async () => {
        const user = userEvent.setup();
        renderForm();

        await user.click(screen.getByLabelText('Categoria'));

        const options = screen.getAllByRole('option');

        expect(options.map((option) => option.textContent)).toEqual([
            'Accessi',
            'Rete',
        ]);
    });

    /*
     * `§3` keeps priority away from the public form because "if the
     * requester chooses, everything is urgent" — a reason that does not
     * apply to the operator taking the call.
     */
    it('lets the operator choose a priority, unlike the public form', () => {
        renderForm();

        expect(screen.getByLabelText('Priorità')).toBeInTheDocument();
    });

    it('starts the priority at normale', async () => {
        const user = userEvent.setup();
        renderForm();

        await fillInTheForm();
        await user.click(screen.getByRole('button', { name: 'Crea ticket' }));

        const [, payload] = vi.mocked(router.post).mock.calls[0];

        expect(payload).toMatchObject({ priority: 'normale' });
    });

    it('sends a filled in ticket to the console intake', async () => {
        const user = userEvent.setup();
        renderForm();

        await fillInTheForm();
        await user.click(screen.getByRole('button', { name: 'Crea ticket' }));

        expect(router.post).toHaveBeenCalledTimes(1);

        const [url, payload] = vi.mocked(router.post).mock.calls[0];

        expect(url).toBe('/tickets');
        expect(payload).toMatchObject({
            name: 'Mario Rossi',
            email: 'mario.rossi@example.com',
            category_id: '2',
            subject: 'Non riesco ad accedere',
            body: 'Chiamata dal numero interno 204: password dimenticata.',
        });
    });

    it('shows what the server refused, next to the field it refused', async () => {
        const user = userEvent.setup();
        renderForm();

        vi.mocked(router.post).mockImplementation(
            (_url: unknown, _payload: unknown, options?: unknown) => {
                (
                    options as {
                        onError?: (errors: Record<string, string>) => void;
                    }
                )?.onError?.({
                    email: 'Indirizzo non valido.',
                });
            },
        );

        await fillInTheForm();
        await user.click(screen.getByRole('button', { name: 'Crea ticket' }));

        expect(
            await screen.findByText('Indirizzo non valido.'),
        ).toBeInTheDocument();
    });
});
