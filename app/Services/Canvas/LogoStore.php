<?php

namespace App\Services\Canvas;

use App\Models\Organisation;
use App\Services\Support\SerialisedWrite;
use App\Services\Support\WritableGuard;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LogoStore
{
    use WritableGuard;

    public const DISK = 'local';

    public const DIRECTORY = 'logos';

    private const MAXIMUM_BYTES = 1048576;

    private const MAXIMUM_PIXELS = 4000;

    public function __construct(private readonly SerialisedWrite $writes) {}

    public function store(Organisation $organisation, UploadedFile $file): Organisation
    {
        $this->assertWritable($organisation);

        [$bytes, $type] = $this->verified($file);
        $image = $this->reencoded($bytes, $type);
        $path = self::DIRECTORY.'/'.Str::random(40).'.'.self::extension($type);

        $previous = $this->writes->run([$organisation], function () use ($organisation, $image, $path) {
            $this->assertWritable($organisation);

            $replaced = $organisation->logo_path;

            Storage::disk(self::DISK)->put($path, $image);
            $organisation->forceFill(['logo_path' => $path])->save();

            return $replaced;
        });

        if (is_string($previous) && $previous !== '' && $previous !== $path) {
            Storage::disk(self::DISK)->delete($previous);
        }

        return $organisation;
    }

    public static function contentType(string $path): string
    {
        return str_ends_with($path, '.png') ? 'image/png' : 'image/jpeg';
    }

    private static function extension(int $type): string
    {
        return $type === IMAGETYPE_PNG ? 'png' : 'jpg';
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function verified(UploadedFile $file): array
    {
        if (! $file->isValid() || $file->getSize() > self::MAXIMUM_BYTES) {
            $this->refuseLogo();
        }

        $bytes = (string) file_get_contents($file->getRealPath());
        $image = @getimagesizefromstring($bytes);

        if ($image === false || ! in_array($image[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) {
            $this->refuseLogo();
        }

        if ($image[0] > self::MAXIMUM_PIXELS || $image[1] > self::MAXIMUM_PIXELS) {
            $this->refuseLogo();
        }

        return [$bytes, $image[2]];
    }

    private function reencoded(string $bytes, int $type): string
    {
        $image = @imagecreatefromstring($bytes);

        if ($image === false) {
            $this->refuseLogo();
        }

        ob_start();

        if ($type === IMAGETYPE_PNG) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            imagepng($image);
        } else {
            imagejpeg($image, null, 90);
        }

        imagedestroy($image);

        return (string) ob_get_clean();
    }

    private function refuseLogo(): never
    {
        throw ValidationException::withMessages(['logo' => __('organisations.logo_invalid')]);
    }
}
