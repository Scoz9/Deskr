import { Head } from '@inertiajs/react';
import CreateCategoryDialog from '@/components/categories/create-category-dialog';
import DeleteCategoryDialog from '@/components/categories/delete-category-dialog';
import EditCategoryDialog from '@/components/categories/edit-category-dialog';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useCan } from '@/hooks/use-can';
import { index as categoriesIndex } from '@/routes/categories';
import type { Category, RoutableTeam } from '@/types';

type CategoriesProps = {
    categories: Category[];
    teams: RoutableTeam[];
};

export default function Categories({ categories, teams }: CategoriesProps) {
    const can = useCan();

    return (
        <>
            <Head title="Categorie" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Card>
                    <CardHeader className="flex flex-row items-start justify-between gap-2">
                        <div className="space-y-1.5">
                            <CardTitle>Categorie</CardTitle>
                            <CardDescription>
                                La tassonomia che il modulo pubblico offre, e il
                                team su cui instrada i ticket
                            </CardDescription>
                        </div>
                        {can('category:create') && (
                            <CreateCategoryDialog teams={teams} />
                        )}
                    </CardHeader>
                    <CardContent>
                        {categories.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Nessuna categoria presente.
                            </p>
                        ) : (
                            <div className="grid gap-2">
                                {categories.map((category) => (
                                    <div
                                        key={category.id}
                                        className="flex items-center justify-between rounded-lg border px-3 py-2"
                                    >
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-medium">
                                                {category.name}
                                            </span>
                                            <Badge variant="secondary">
                                                {category.team.name}
                                            </Badge>
                                            <Badge variant="outline">
                                                {category.tickets_count} ticket
                                            </Badge>
                                        </div>
                                        <div className="flex items-center">
                                            {can('category:update') && (
                                                <EditCategoryDialog
                                                    category={category}
                                                    teams={teams}
                                                />
                                            )}
                                            {can('category:delete') && (
                                                <DeleteCategoryDialog
                                                    category={category}
                                                />
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

Categories.layout = {
    breadcrumbs: [
        {
            title: 'Categorie',
            href: categoriesIndex(),
        },
    ],
};
