import { useFlashMessages } from '@/hooks/use-flash-toast';

/**
 * Mount point for the CRUD flash toasts. Renders nothing — it exists only to
 * sit inside the Inertia <App> tree, where useFlashMessages() can reach the
 * page context.
 *
 * The <Toaster /> that paints the toasts stays in app.tsx, outside that tree:
 * sonner's toast() queue is a module-level singleton, so emitter and renderer
 * need not be co-located.
 */
export function FlashMessages() {
    useFlashMessages();

    return null;
}
