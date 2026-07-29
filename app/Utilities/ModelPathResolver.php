<?php

namespace App\Utilities;

use Illuminate\Support\Facades\File;

class ModelPathResolver
{
    /**
     * Get all PHP file paths inside the given directory, recursively.
     *
     * @return array<int, string>
     */
    public function getAllPaths(string $directory): array
    {
        if (! File::isDirectory($directory)) {
            return [];
        }

        return collect(File::allFiles($directory))
            ->filter(fn ($file): bool => $file->getExtension() === 'php')
            ->map(fn ($file): string => $file->getPathname())
            ->values()
            ->all();
    }
}
