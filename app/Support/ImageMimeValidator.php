<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

class ImageMimeValidator
{
    public static function assertAllowed(UploadedFile $file): void
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        abort_unless(in_array($extension, $allowed, true), 422);

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file->getRealPath());

        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true), 422);
    }
}
