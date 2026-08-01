import { router } from '@inertiajs/react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import SupportCreate from '@/pages/support/create';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    router: { post: vi.fn() },
}));

vi.mock('react-i18next', () => ({
    useTranslation: () => ({ t: (key: string) => key }),
}));

const categories = [
    { id: 1, name: 'Accessi' },
    { id: 2, name: 'Rete' },
];

function renderForm(reference: string | null = null) {
    return render(
        <SupportCreate categories={categories} reference={reference} />,
    );
}

beforeEach(() => {
    vi.mocked(router.post).mockClear();
});

async function fillInTheForm(): Promise<void> {
    const user = userEvent.setup();

    await user.type(screen.getByLabelText('support.fields.name'), 'Anna Rossi');
    await user.type(
        screen.getByLabelText('support.fields.email'),
        'anna.rossi@example.com',
    );
    await user.selectOptions(
        screen.getByLabelText('support.fields.category'),
        '2',
    );
    await user.type(
        screen.getByLabelText('support.fields.subject'),
        'La stampante non risponde',
    );
    await user.type(
        screen.getByLabelText('support.fields.body'),
        'Da stamattina la stampante del secondo piano non stampa.',
    );
}

describe('the public intake form', () => {
    it('asks for everything the intake needs and nothing else', () => {
        renderForm();

        expect(
            screen.getByLabelText('support.fields.name'),
        ).toBeInTheDocument();
        expect(
            screen.getByLabelText('support.fields.email'),
        ).toBeInTheDocument();
        expect(
            screen.getByLabelText('support.fields.category'),
        ).toBeInTheDocument();
        expect(
            screen.getByLabelText('support.fields.subject'),
        ).toBeInTheDocument();
        expect(
            screen.getByLabelText('support.fields.body'),
        ).toBeInTheDocument();
    });

    /*
     * If the requester chooses, everything is urgent (§3): the public form does
     * not get to say how important the request is.
     */
    it('never asks the requester for a priority', () => {
        renderForm();

        expect(screen.queryByLabelText('support.fields.priority')).toBeNull();
    });

    it('offers the categories the server sent, in the order it sent them', () => {
        renderForm();

        const options = screen.getAllByRole('option');

        expect(options.map((option) => option.textContent)).toEqual([
            'support.fields.categoryPlaceholder',
            'Accessi',
            'Rete',
        ]);
    });

    it('tells the person what is missing instead of sending an empty request', async () => {
        const user = userEvent.setup();
        renderForm();

        await user.click(
            screen.getByRole('button', { name: 'support.submit' }),
        );

        expect(
            await screen.findAllByText('support.errors.required'),
        ).toHaveLength(5);
    });

    it('refuses an address that is not one', async () => {
        const user = userEvent.setup();
        renderForm();

        await fillInTheForm();
        await user.clear(screen.getByLabelText('support.fields.email'));
        await user.type(screen.getByLabelText('support.fields.email'), 'anna');
        await user.click(
            screen.getByRole('button', { name: 'support.submit' }),
        );

        expect(screen.getByText('support.errors.email')).toBeInTheDocument();
    });

    it('stops complaining once the field is filled in', async () => {
        const user = userEvent.setup();
        renderForm();

        await user.click(
            screen.getByRole('button', { name: 'support.submit' }),
        );
        await fillInTheForm();
        await user.click(
            screen.getByRole('button', { name: 'support.submit' }),
        );

        expect(screen.queryByText('support.errors.required')).toBeNull();
        expect(screen.queryByText('support.errors.email')).toBeNull();
    });

    /*
     * The honeypot is bait: it has to be in the markup a script reads and out of
     * the form a person fills, which means out of the tab order and out of the
     * accessibility tree.
     */
    it('carries a honeypot no person can reach', () => {
        const { container } = renderForm();

        const honeypot = container.querySelector('input[name="website"]');

        expect(honeypot).not.toBeNull();
        expect(honeypot).toHaveAttribute('tabindex', '-1');
        expect(honeypot).toHaveAttribute('autocomplete', 'off');
        expect(honeypot?.closest('[aria-hidden="true"]')).not.toBeNull();
    });

    it('sends a filled in request to the intake', async () => {
        const user = userEvent.setup();
        renderForm();

        await fillInTheForm();
        await user.click(
            screen.getByRole('button', { name: 'support.submit' }),
        );

        expect(router.post).toHaveBeenCalledTimes(1);

        const [url, payload] = vi.mocked(router.post).mock.calls[0];

        expect(url).toBe('/assistenza');
        expect(payload).toMatchObject({
            name: 'Anna Rossi',
            email: 'anna.rossi@example.com',
            categoryId: '2',
            subject: 'La stampante non risponde',
            body: 'Da stamattina la stampante del secondo piano non stampa.',
            website: '',
        });
    });

    /*
     * The browser check is a courtesy and the server one is the defence, but a
     * request the browser already knows is incomplete is a round trip nobody
     * needs.
     */
    it('does not bother the server with a request it can already see is empty', async () => {
        const user = userEvent.setup();
        renderForm();

        await user.click(
            screen.getByRole('button', { name: 'support.submit' }),
        );

        expect(router.post).not.toHaveBeenCalled();
    });

    /*
     * What the server refuses is said in the server's own words: the cap on open
     * tickets is a rule the browser has no way to know about.
     */
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
                    email: 'Hai già diverse richieste aperte.',
                });
            },
        );

        await fillInTheForm();
        await user.click(
            screen.getByRole('button', { name: 'support.submit' }),
        );

        expect(
            await screen.findByText('Hai già diverse richieste aperte.'),
        ).toBeInTheDocument();
    });

    /*
     * The reference is the receipt of the request: until the confirmation email
     * of step 25 exists, it is the only thing the requester walks away with.
     */
    it('shows the reference of the ticket just opened', () => {
        renderForm('DSK-000123');

        expect(screen.getByText(/DSK-000123/)).toBeInTheDocument();
    });

    it('says nothing about a reference nobody just earned', () => {
        renderForm();

        expect(screen.queryByText(/DSK-/)).toBeNull();
    });
});
