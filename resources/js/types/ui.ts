import type { ReactNode } from 'react';
import type { BreadcrumbItem } from '@/types/navigation';

export type AppLayoutProps = {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
};

export type AppVariant = 'header' | 'sidebar';

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
};

/**
 * A flash message shared by scrapkit/laravel-flash-messages as the
 * `flashMessages` Inertia prop. `message` is resolved server-side in the
 * current locale.
 */
export type FlashMessage = {
    level: 'success' | 'info' | 'warning' | 'error';
    message: string;
    id?: string | null;
    dismissible?: boolean;
};

export type AuthLayoutProps = {
    children?: ReactNode;
    name?: string;
    title?: string;
    description?: string;
};
