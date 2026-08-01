import {
    channelLabels,
    priorityLabels,
    statusLabels,
} from '@/components/tickets/tickets-table';
import type {
    TicketChannel,
    TicketPriority,
    TicketStatus,
} from '@/components/tickets/tickets-table';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export type TicketFilters = {
    status: TicketStatus | null;
    priority: TicketPriority | null;
    channel: TicketChannel | null;
    teamId: number | null;
    assignee: number | 'unassigned' | null;
};

export type FilterOption = {
    id: number;
    name: string;
};

const ALL = 'all';
const UNASSIGNED = 'unassigned';

type TicketsFiltersProps = {
    filters: TicketFilters;
    teams: FilterOption[];
    assignees: FilterOption[];
    onChange: (patch: Partial<TicketFilters>) => void;
};

export default function TicketsFilters({
    filters,
    teams,
    assignees,
    onChange,
}: TicketsFiltersProps) {
    return (
        <div className="flex flex-wrap items-end gap-3">
            <div className="grid w-44 gap-1.5">
                <Label htmlFor="filter-status">Stato</Label>
                <Select
                    value={filters.status ?? ALL}
                    onValueChange={(value) =>
                        onChange({
                            status:
                                value === ALL ? null : (value as TicketStatus),
                        })
                    }
                >
                    <SelectTrigger id="filter-status" size="sm">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL}>Tutti</SelectItem>
                        {Object.entries(statusLabels).map(([value, label]) => (
                            <SelectItem key={value} value={value}>
                                {label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            <div className="grid w-40 gap-1.5">
                <Label htmlFor="filter-priority">Priorità</Label>
                <Select
                    value={filters.priority ?? ALL}
                    onValueChange={(value) =>
                        onChange({
                            priority:
                                value === ALL
                                    ? null
                                    : (value as TicketPriority),
                        })
                    }
                >
                    <SelectTrigger id="filter-priority" size="sm">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL}>Tutte</SelectItem>
                        {Object.entries(priorityLabels).map(
                            ([value, label]) => (
                                <SelectItem key={value} value={value}>
                                    {label}
                                </SelectItem>
                            ),
                        )}
                    </SelectContent>
                </Select>
            </div>

            <div className="grid w-40 gap-1.5">
                <Label htmlFor="filter-channel">Canale</Label>
                <Select
                    value={filters.channel ?? ALL}
                    onValueChange={(value) =>
                        onChange({
                            channel:
                                value === ALL ? null : (value as TicketChannel),
                        })
                    }
                >
                    <SelectTrigger id="filter-channel" size="sm">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL}>Tutti</SelectItem>
                        {Object.entries(channelLabels).map(([value, label]) => (
                            <SelectItem key={value} value={value}>
                                {label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            <div className="grid w-48 gap-1.5">
                <Label htmlFor="filter-team">Team</Label>
                <Select
                    value={filters.teamId?.toString() ?? ALL}
                    onValueChange={(value) =>
                        onChange({
                            teamId: value === ALL ? null : Number(value),
                        })
                    }
                >
                    <SelectTrigger id="filter-team" size="sm">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL}>Tutti</SelectItem>
                        {teams.map((team) => (
                            <SelectItem
                                key={team.id}
                                value={team.id.toString()}
                            >
                                {team.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            <div className="grid w-48 gap-1.5">
                <Label htmlFor="filter-assignee">Assegnatario</Label>
                <Select
                    value={filters.assignee?.toString() ?? ALL}
                    onValueChange={(value) =>
                        onChange({
                            assignee:
                                value === ALL
                                    ? null
                                    : value === UNASSIGNED
                                      ? UNASSIGNED
                                      : Number(value),
                        })
                    }
                >
                    <SelectTrigger id="filter-assignee" size="sm">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL}>Tutti</SelectItem>
                        <SelectItem value={UNASSIGNED}>
                            Non assegnato
                        </SelectItem>
                        {assignees.map((assignee) => (
                            <SelectItem
                                key={assignee.id}
                                value={assignee.id.toString()}
                            >
                                {assignee.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
        </div>
    );
}
