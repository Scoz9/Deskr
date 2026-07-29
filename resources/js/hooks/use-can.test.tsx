import { usePage } from '@inertiajs/react';
import { renderHook } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useCan } from '@/hooks/use-can';

vi.mock('@inertiajs/react', () => ({
    usePage: vi.fn(),
}));

function withAuth(isSuperAdmin: boolean, permissions: string[]): void {
    vi.mocked(usePage).mockReturnValue({
        props: { auth: { isSuperAdmin, permissions } },
    } as never);
}

describe('useCan', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('grants a permission the user holds', () => {
        withAuth(false, ['ticket:viewAny']);

        const { result } = renderHook(() => useCan());

        expect(result.current('ticket:viewAny')).toBe(true);
    });

    it('denies a permission the user does not hold', () => {
        withAuth(false, ['ticket:viewAny']);

        const { result } = renderHook(() => useCan());

        expect(result.current('ticket:delete')).toBe(false);
    });

    it('grants every permission to a super admin', () => {
        withAuth(true, []);

        const { result } = renderHook(() => useCan());

        expect(result.current('ticket:delete')).toBe(true);
    });
});
