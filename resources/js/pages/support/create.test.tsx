import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import SupportCreate from '@/pages/support/create';

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
}));

vi.mock('react-i18next', () => ({
    useTranslation: () => ({ t: (key: string) => key }),
}));

const categories = [
    { id: 1, name: 'Accessi' },
    { id: 2, name: 'Rete' },
];

function renderForm() {
    return render(<SupportCreate categories={categories} />);
}

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

    /*
     * Step 22 is the form and nothing else: the wiring to `CreateTicket` is step
     * 23, and until then a valid form must not try to reach the server.
     */
    it('sends nothing anywhere yet', async () => {
        const user = userEvent.setup();
        const { container } = renderForm();

        expect(container.querySelector('form')).not.toHaveAttribute('action');

        await fillInTheForm();
        await user.click(
            screen.getByRole('button', { name: 'support.submit' }),
        );

        expect(screen.queryByText(/support\.errors/)).toBeNull();
    });
});
