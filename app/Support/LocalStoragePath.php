<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

final class LocalStoragePath
{
    public static function resolve(string $path): string
    {
        $disk = Storage::disk('local');
        $resolved = realpath($disk->path($path));
        $root = realpath($disk->path(''));

        abort_unless($resolved !== false && $root !== false && is_file($resolved), 404);

        $rootPrefix = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        $isWithinRoot = DIRECTORY_SEPARATOR === '\\'
            ? strncasecmp($resolved, $rootPrefix, strlen($rootPrefix)) === 0
            : str_starts_with($resolved, $rootPrefix);

        abort_unless($isWithinRoot, 404);

        return $resolved;
    }
}
