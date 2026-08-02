import { useState } from 'react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Organization } from '@/types';

/**
 * Radix Select has no empty value, so "no company" travels as this sentinel
 * and is turned back into an empty string on the way out — which Laravel's
 * ConvertEmptyStringsToNull then hands the controller as null.
 */
const NONE = 'none';

type OrganizationSelectProps = {
    id: string;
    organizations: Organization[];
    defaultValue?: number | null;
};

export default function OrganizationSelect({
    id,
    organizations,
    defaultValue,
}: OrganizationSelectProps) {
    const [organization, setOrganization] = useState(
        defaultValue?.toString() ?? NONE,
    );

    return (
        <>
            {/* Radix Select non invia il valore nel form: l'organizzazione
                selezionata viene inviata tramite questo hidden input. */}
            <input
                type="hidden"
                name="organization_id"
                value={organization === NONE ? '' : organization}
            />
            <Select value={organization} onValueChange={setOrganization}>
                <SelectTrigger id={id} data-test={`${id}-trigger`}>
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={NONE}>Nessuna</SelectItem>
                    {organizations.map((option) => (
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
