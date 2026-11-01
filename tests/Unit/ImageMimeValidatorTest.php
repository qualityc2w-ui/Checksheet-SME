<?php

namespace Tests\Unit;

use App\Support\ImageMimeValidator;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ImageMimeValidatorTest extends TestCase
{
    public function test_it_accepts_a_supported_image_detected_from_file_contents(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'image.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jXioAAAAASUVORK5CYII=')
        );

        $this->assertSame('image/png', (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath()));
        ImageMimeValidator::assertAllowed($file);
    }

    public function test_it_rejects_non_image_contents_even_when_the_filename_has_an_image_extension(): void
    {
        $file = UploadedFile::fake()->createWithContent('image.jpg', 'not an image');

        try {
            ImageMimeValidator::assertAllowed($file);
            $this->fail('Expected unsupported MIME content to be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
    }
}
