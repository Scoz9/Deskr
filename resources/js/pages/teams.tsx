import { Head } from '@inertiajs/react';
import CreateTeamDialog from '@/components/teams/create-team-dialog';
import DeleteTeamDialog from '@/components/teams/delete-team-dialog';
import RenameTeamDialog from '@/components/teams/rename-team-dialog';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useCan } from '@/hooks/use-can';
import { index as teamsIndex } from '@/routes/teams';
import type { Team } from '@/types';

type TeamsProps = {
    teams: Team[];
};

export default function Teams({ teams }: TeamsProps) {
    const can = useCan();

    return (
        <>
            <Head title="Team" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Card>
                    <CardHeader className="flex flex-row items-start justify-between gap-2">
                        <div className="space-y-1.5">
                            <CardTitle>Team</CardTitle>
                            <CardDescription>
                                I gruppi di operatori su cui le categorie
                                instradano i ticket
                            </CardDescription>
                        </div>
                        {can('team:create') && <CreateTeamDialog />}
                    </CardHeader>
                    <CardContent>
                        {teams.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Nessun team presente.
                            </p>
                        ) : (
                            <div className="grid gap-2">
                                {teams.map((team) => (
                                    <div
                                        key={team.id}
                                        className="flex items-center justify-between rounded-lg border px-3 py-2"
                                    >
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-medium">
                                                {team.name}
                                            </span>
                                            <Badge variant="secondary">
                                                {team.members_count} operatori
                                            </Badge>
                                            <Badge variant="outline">
                                                {team.categories_count}{' '}
                                                categorie
                                            </Badge>
                                            <Badge variant="outline">
                                                {team.tickets_count} ticket
                                            </Badge>
                                        </div>
                                        <div className="flex items-center">
                                            {can('team:update') && (
                                                <RenameTeamDialog team={team} />
                                            )}
                                            {can('team:delete') && (
                                                <DeleteTeamDialog team={team} />
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Teams.layout = {
    breadcrumbs: [
        {
            title: 'Team',
            href: teamsIndex(),
        },
    ],
};
