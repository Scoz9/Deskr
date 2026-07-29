/**
 * Mirrors the API resources of scrapkit/notification-kit.
 * Published stub: yours to edit once it lives in your app.
 */

export type TemplateType = 'email' | 'notification';

export type OutboxStatus =
    'pending' | 'approved' | 'cancelled' | 'sent' | 'failed';

export interface Placeholder {
    key: string;
    description: string;
    example: string | null;
}

export interface Template {
    key: string;
    type: TemplateType;
    name: string;
    description: string | null;
    subject: string | null;
    body: string | null;
    default_subject: string | null;
    default_body: string;
    is_customized: boolean;
    placeholders: Placeholder[];
    sample_data: Record<string, unknown>;
    metadata: Record<string, unknown>;
    requires_confirmation: boolean;
    /** Only mailables sent through the kit can be held for approval. */
    supports_confirmation: boolean;
    archived_at: string | null;
    updated_at: string | null;
}

export interface TemplateVersion {
    id: number;
    subject: string | null;
    body: string | null;
    metadata: Record<string, unknown>;
    requires_confirmation: boolean;
    edited_by: string | null;
    created_at: string | null;
}

export interface Recipient {
    type: 'to' | 'cc' | 'bcc';
    address: string;
    name: string | null;
}

export interface OutboxMessage {
    uuid: string;
    template_key: string;
    template_name: string | null;
    mailable_class: string;
    rendered_subject: string;
    rendered_body_html: string;
    recipients: Recipient[];
    status: OutboxStatus;
    requested_by: string | null;
    decided_by: string | null;
    decided_at: string | null;
    sent_at: string | null;
    error: string | null;
    created_at: string | null;
}

export interface Preview {
    subject: string | null;
    body_html: string;
    missing_placeholders: string[];
}

export interface Paginated<T> {
    data: T[];
    meta: { current_page: number; last_page: number; total: number };
}

export interface TemplateFilters {
    type?: TemplateType;
    archived?: 'only' | 'with';
    requires_confirmation?: boolean;
    search?: string;
}

export interface OutboxFilters {
    status?: OutboxStatus;
    template_key?: string;
    search?: string;
}

export interface TemplateContentPayload {
    subject: string | null;
    body: string | null;
    metadata?: Record<string, unknown>;
    requires_confirmation: boolean;
}
