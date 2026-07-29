import { useState } from 'react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { AssignableRole } from '@/types';

type RoleSelectProps = {
    id: string;
    roles: AssignableRole[];
    defaultValue?: string;
};

export default function RoleSelect({
    id,
    roles,
    defaultValue,
}: RoleSelectProps) {
    const [role, setRole] = useState(defaultValue ?? '');

    return (
        <>
            {/* Radix Select non invia il valore nel form: il ruolo
                selezionato viene inviato tramite questo hidden input. */}
            <input type="hidden" name="role" value={role} />
            <Select value={role} onValueChange={setRole}>
                <SelectTrigger id={id} data-test={`${id}-trigger`}>
                    <SelectValue placeholder="Seleziona un ruolo" />
                </SelectTrigger>
                <SelectContent>
                    {roles.map((assignableRole) => (
                        <SelectItem
                            key={assignableRole.id}
                            value={assignableRole.name}
                        >
                            {assignableRole.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </>
    );
}
