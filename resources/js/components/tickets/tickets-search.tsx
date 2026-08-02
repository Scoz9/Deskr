import { useEffect, useState } from 'react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const DEBOUNCE_MS = 400;

type TicketsSearchProps = {
    value: string | null;
    onChange: (value: string | null) => void;
};

/**
 * The search box of step 33: full-text on subject, thread, requester and
 * organisation, on Postgres' own dictionary (§3).
 *
 * Debounced locally before it ever reaches `onChange` — a request per
 * keystroke would hammer the console for every letter typed, and the
 * server has nothing useful to say about "stamp" that it doesn't already
 * say about "stampante".
 */
export default function TicketsSearch({ value, onChange }: TicketsSearchProps) {
    const [term, setTerm] = useState(value ?? '');

    // Keeps the box in step with a search that changed from outside — the
    // browser's back button, or a filter reset elsewhere on the page. Set
    // during render rather than in an effect: an effect would commit the
    // stale value first and only correct it a render later.
    const [syncedValue, setSyncedValue] = useState(value);

    if (value !== syncedValue) {
        setSyncedValue(value);
        setTerm(value ?? '');
    }

    useEffect(() => {
        const trimmed = term.trim();

        if (trimmed === (value ?? '')) {
            return;
        }

        const timeout = setTimeout(() => {
            onChange(trimmed === '' ? null : trimmed);
        }, DEBOUNCE_MS);

        return () => clearTimeout(timeout);
        // Only the box's own value should restart the timer; re-running it
        // when `value` changes would fire again right after the visit that
        // just carried this same term back from the server.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [term]);

    return (
        <div className="grid w-64 gap-1.5">
            <Label htmlFor="tickets-search">Cerca</Label>
            <Input
                id="tickets-search"
                type="search"
                placeholder="Oggetto, richiedente, azienda…"
                value={term}
                onChange={(event) => setTerm(event.target.value)}
            />
        </div>
    );
}
