<?php

namespace Tests\Unit;

use App\Support\LocalStoragePath;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class LocalStoragePathTest extends TestCase
{
    public function test_it_rejects_files_outside_the_local_storage_root(): void
    {
        Storage::fake('local');
        $root = Storage::disk('local')->path('');
        $outsidePath = dirname($root).DIRECTORY_SEPARATOR.'outside-storage-test.txt';

        file_put_contents($outsidePath, 'outside');

        try {
            $this->expectException(HttpException::class);
            LocalStoragePath::resolve('../outside-storage-test.txt');
        } finally {
            if (is_file($outsidePath)) {
                unlink($outsidePath);
            }
        }
    }
}
