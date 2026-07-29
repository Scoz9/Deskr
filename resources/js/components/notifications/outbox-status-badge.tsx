import { Badge } from '@/components/ui/badge';
import type { OutboxStatus } from '@/lib/notification-kit/types';

const LABELS: Record<OutboxStatus, string> = {
    pending: 'Da confermare',
    approved: 'Approvata',
    cancelled: 'Annullata',
    sent: 'Inviata',
    failed: 'Fallita',
};

const VARIANTS: Record<
    OutboxStatus,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    pending: 'default',
    approved: 'secondary',
    cancelled: 'outline',
    sent: 'secondary',
    failed: 'destructive',
};

export default function OutboxStatusBadge({
    status,
}: {
    status: OutboxStatus;
}) {
    return <Badge variant={VARIANTS[status]}>{LABELS[status]}</Badge>;
}
