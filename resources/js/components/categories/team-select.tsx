import { useState } from 'react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { RoutableTeam } from '@/types';

type TeamSelectProps = {
    id: string;
    teams: RoutableTeam[];
    defaultValue?: number;
};

/**
 * Which team a category routes to. Unlike the organization of a user, there
 * is no "none": a category without a destination would leave the routing
 * with nowhere to go, which is why the column is not nullable either.
 */
export default function TeamSelect({
    id,
    teams,
    defaultValue,
}: TeamSelectProps) {
    const [team, setTeam] = useState(defaultValue?.toString() ?? '');

    return (
        <>
            {/* Radix Select non invia il valore nel form: il team
                selezionato viene inviato tramite questo hidden input. */}
            <input type="hidden" name="team_id" value={team} />
            <Select value={team} onValueChange={setTeam}>
                <SelectTrigger id={id} data-test={`${id}-trigger`}>
                    <SelectValue placeholder="Seleziona un team" />
                </SelectTrigger>
                <SelectContent>
                    {teams.map((option) => (
                        <SelectItem
                            key={option.id}
                            value={option.id.toString()}
                        >
                            {option.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </>
    );
}
