import type {
    OutboxFilters,
    OutboxMessage,
    Paginated,
    Preview,
    Template,
    TemplateContentPayload,
    TemplateFilters,
    TemplateVersion,
} from './types';

/**
 * Typed client for the notification-kit JSON API.
 * Published stub: change BASE_PATH if you changed notification-kit.routes.prefix.
 */
const BASE_PATH = '/notification-kit/api/v1';

function csrfToken(): string {
    return (
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? ''
    );
}

async function request<T>(
    method: string,
    path: string,
    body?: unknown,
): Promise<T> {
    const response = await fetch(`${BASE_PATH}${path}`, {
        method,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
        },
        credentials: 'same-origin',
        body: body === undefined ? undefined : JSON.stringify(body),
    });

    if (!response.ok) {
        const payload = (await response.json().catch(() => ({}))) as {
            message?: string;
        };

        throw new Error(
            payload.message ?? `Request failed with status ${response.status}`,
        );
    }

    return (await response.json()) as T;
}

function query(filters: Record<string, unknown>): string {
    const params = new URLSearchParams();

    Object.entries(filters).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
            params.set(key, String(value));
        }
    });

    const serialized = params.toString();

    return serialized === '' ? '' : `?${serialized}`;
}

export const notificationKit = {
    templates: {
        list: (filters: TemplateFilters = {}) =>
            request<Paginated<Template>>(
                'GET',
                `/templates${query({ ...filters })}`,
            ),

        show: (key: string) =>
            request<{ data: Template }>('GET', `/templates/${key}`),

        updateContent: (key: string, payload: TemplateContentPayload) =>
            request<{ data: Template }>(
                'PUT',
                `/templates/${key}/content`,
                payload,
            ),

        archive: (key: string) =>
            request<{ data: Template }>('POST', `/templates/${key}/archive`),

        unarchive: (key: string) =>
            request<{ data: Template }>('POST', `/templates/${key}/unarchive`),

        versions: (key: string) =>
            request<Paginated<TemplateVersion>>(
                'GET',
                `/templates/${key}/versions`,
            ),

        preview: (
            key: string,
            draft: { subject?: string | null; body?: string | null } = {},
        ) =>
            request<{ data: Preview }>(
                'POST',
                `/templates/${key}/preview`,
                draft,
            ),
    },

    outbox: {
        list: (filters: OutboxFilters = {}) =>
            request<Paginated<OutboxMessage>>(
                'GET',
                `/outbox${query({ ...filters })}`,
            ),

        show: (uuid: string) =>
            request<{ data: OutboxMessage }>('GET', `/outbox/${uuid}`),

        approve: (uuid: string) =>
            request<{ data: OutboxMessage }>('POST', `/outbox/${uuid}/approve`),

        cancel: (uuid: string) =>
            request<{ data: OutboxMessage }>('POST', `/outbox/${uuid}/cancel`),
    },
};
