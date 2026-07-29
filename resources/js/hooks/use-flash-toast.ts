import { router, usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { toast } from 'sonner';
import type { SharedData } from '@/types';
import type { FlashToast } from '@/types/ui';

/**
 * Legacy Inertia flash events: non-CRUD toasts still emitted with
 * Inertia::flash() (e.g. suspend/unsuspend, password, profile).
 *
 * Subscribes to the router singleton, so it needs no page context and may run
 * outside the Inertia <App> tree.
 */
export function useFlashToastEvents(): void {
    useEffect(() => {
        return router.on('flash', (event) => {
            const data = (event as CustomEvent).detail?.flash?.toast as
                FlashToast | undefined;

            if (!data) {
                return;
            }

            toast[data.type](data.message);
        });
    }, []);
}

/**
 * Convention-driven CRUD flash from scrapkit/laravel-flash-messages, shared as
 * the `flashMessages` prop and resolved server-side in the current locale.
 *
 * Reads page props through usePage(), so it must be mounted inside the Inertia
 * <App> tree — see <FlashMessages />.
 */
export function useFlashMessages(): void {
    const messages = usePage<SharedData>().props.flashMessages;
    const lastSignature = useRef<string | null>(null);

    useEffect(() => {
        if (!messages || messages.length === 0) {
            return;
        }

        const signature = JSON.stringify(
            messages.map((message) => [
                message.id,
                message.level,
                message.message,
            ]),
        );

        if (signature === lastSignature.current) {
            return;
        }

        lastSignature.current = signature;

        messages.forEach((message) => {
            toast[message.level](message.message);
        });
    }, [messages]);
}
