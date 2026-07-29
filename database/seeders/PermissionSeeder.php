<?php

namespace Database\Seeders;

use App\Utilities\ModelPathResolver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Abilities generated for every application model.
     *
     * @var list<string>
     */
    private const MODEL_ABILITIES = ['viewAny', 'view', 'create', 'update', 'delete'];

    /**
     * @return array<int, string>
     */
    public static function getModelPermissions(): array
    {
        $modelsPaths = (new ModelPathResolver)->getAllPaths(app_path('Models'));

        return collect($modelsPaths)
            ->map(fn (string $file): string => Str::camel(basename($file, '.php')))
            ->flatMap(fn (string $model): array => array_map(
                fn (string $ability): string => "{$model}:{$ability}",
                self::MODEL_ABILITIES,
            ))
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function getCustomPermissions(): array
    {
        return [
            'permission:viewAny',
            'user:suspend',
            'notification:viewAny',
            'notification:update',
            'notification:archive',
            'notification:approve',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function getPermissionNames(): array
    {
        return [
            ...self::getModelPermissions(),
            ...self::getCustomPermissions(),
        ];
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissionNames = self::getPermissionNames();

        Permission::query()->whereNotIn('name', $permissionNames)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($permissionNames as $permission) {
            Permission::createOrFirst(['name' => $permission]);
        }
    }
}
