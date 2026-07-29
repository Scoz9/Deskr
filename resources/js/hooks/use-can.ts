import { usePage } from '@inertiajs/react';
import type { SharedData } from '@/types';

export function useCan() {
    const { auth } = usePage<SharedData>().props;

    return (permission: string): boolean =>
        auth.isSuperAdmin || auth.permissions.includes(permission);
}
