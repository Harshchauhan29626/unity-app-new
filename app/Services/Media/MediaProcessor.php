<?php

namespace App\Services\Media;

use App\Exceptions\MediaProcessingException;
use App\Support\Media\Probe;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class MediaProcessor
{
    public function __construct(private readonly Probe $probe)
    {
    }

    /**
        * @param  string  $type  image|video
        * @return array{path:string,mime_type:string,size_bytes:int|null,width:int|null,height:int|null,duration:int|null}
        */
    public function optimize(string $sourcePath, string $type, ?string $mimeType = null): array
    {
        if ($type === 'image') {
            return $this->processImage($sourcePath, $mimeType);
        }

        if ($type === 'video') {
            return $this->processVideo($sourcePath, $mimeType);
        }

        throw new MediaProcessingException('Unsupported media type.');
    }

    private function processImage(string $sourcePath, ?string $mime): array
    {
        if (! $this->probe->imagickAvailable() && ! $this->probe->gdAvailable()) {
            throw new MediaProcessingException('Image optimization requires GD or Imagick. Upload rejected.');
        }

        $maxWidth = (int) config('media.image.max_width', 1600);
        $maxHeight = (int) config('media.image.max_height', 1600);
        $quality = (int) config('media.image.quality', 80);

        $hasAlpha = $this->imageHasAlpha($sourcePath, $mime);
        $preferred = config('media.image.format_preference', 'webp');
        $webpSupported = $this->webpSupported();

        $targetFormat = $this->decideImageFormat($preferred, $webpSupported, $hasAlpha);
        $targetMime = $this->mimeForFormat($targetFormat);
        $destination = $this->tempFilePath($targetFormat);

        [$width, $height] = $this->resizeImage($sourcePath, $destination, $targetFormat, $maxWidth, $maxHeight, $quality, $mime);

        return [
            'path' => $destination,
            'mime_type' => $targetMime,
            'size_bytes' => @filesize($destination) ?: null,
            'width' => $width,
            'height' => $height,
            'duration' => null,
        ];
    }

    private function processVideo(string $sourcePath, ?string $mimeType = null): array
    {
        if (! $this->probe->ffmpegAvailable()) {
            throw new MediaProcessingException('Video optimization requires FFmpeg. Upload rejected.');
        }

        $destination = $this->tempFilePath('mp4');
        $scaleFilter = "scale='min(720,iw)':-2";

        $process = new Process([
            'ffmpeg',
            '-y',
            '-i',
            $sourcePath,
            '-vf',
            $scaleFilter,
            '-c:v',
            'libx264',
            '-preset',
            'veryfast',
            '-crf',
            '30',
            '-c:a',
            'aac',
            '-b:a',
            '96k',
            '-movflags',
            '+faststart',
            $destination,
        ]);

        $process->setTimeout((int) config('media.video.timeout', 180));
        $process->run();

        $originalSize = @filesize($sourcePath) ?: null;
        $optimizedSize = @filesize($destination) ?: null;

        if (! $process->isSuccessful()) {
            Log::warning('FFmpeg failed; keeping original video.', [
                'command' => $process->getCommandLine(),
                'stdout' => $process->getOutput(),
                'stderr' => $process->getErrorOutput(),
                'original_path' => $sourcePath,
                'optimized_path' => $destination,
                'original_size' => $originalSize,
                'optimized_size' => $optimizedSize,
                'final_selected_file' => $sourcePath,
            ]);

            @unlink($destination);

            return $this->buildVideoPayload($sourcePath, $mimeType);
        }

        $finalPath = $destination;

        if ($optimizedSize === null || $originalSize === null || $optimizedSize >= $originalSize) {
            @unlink($destination);
            $finalPath = $sourcePath;
        }

        Log::info('Video optimization completed.', [
            'original_path' => $sourcePath,
            'optimized_path' => $destination,
            'original_size' => $originalSize,
            'optimized_size' => $optimizedSize,
            'final_selected_file' => $finalPath,
        ]);

        return $this->buildVideoPayload($finalPath, $mimeType);
    }

    private function buildVideoPayload(string $path, ?string $mimeType = null): array
    {
        $meta = $this->probe->videoMetadata($path);

        return [
            'path' => $path,
            'mime_type' => $mimeType ?: ($this->probe->mimeType($path) ?: 'video/mp4'),
            'size_bytes' => @filesize($path) ?: null,
            'width' => $meta['width'] ?? null,
            'height' => $meta['height'] ?? null,
            'duration' => $meta['duration'] ?? null,
        ];
    }

    private function resizeImage(string $source, string $destination, string $format, int $maxWidth, int $maxHeight, int $quality, ?string $mime): array
    {
        if ($this->probe->imagickAvailable()) {
            return $this->resizeWithImagick($source, $destination, $format, $maxWidth, $maxHeight, $quality);
        }

        return $this->resizeWithGd($source, $destination, $format, $maxWidth, $maxHeight, $quality, $mime);
    }

    private function resizeWithImagick(string $source, string $destination, string $format, int $maxWidth, int $maxHeight, int $quality): array
    {
        try {
            $image = new \Imagick($source);
        } catch (\Throwable $e) {
            Log::warning('Imagick failed to read image', ['error' => $e->getMessage()]);
            throw new MediaProcessingException('Unable to read image with Imagick.');
        }

        if (method_exists($image, 'autoOrient')) {
            $image->autoOrient();
        }

        $image->stripImage();

        [$newWidth, $newHeight] = $this->fitWithin($image->getImageWidth(), $image->getImageHeight(), $maxWidth, $maxHeight);
        $image->thumbnailImage($newWidth, $newHeight, true);

        $image->setImageFormat($format === 'jpg' ? 'jpeg' : $format);
        $image->setImageCompressionQuality($quality);

        $this->ensureDirectory($destination);
        $image->writeImage($destination);
        $image->clear();
        $image->destroy();

        return [$newWidth, $newHeight];
    }

    private function resizeWithGd(string $source, string $destination, string $format, int $maxWidth, int $maxHeight, int $quality, ?string $mime): array
    {
        $contents = @file_get_contents($source);
        if ($contents === false) {
            throw new MediaProcessingException('Unable to read image for optimization.');
        }

        $image = @imagecreatefromstring($contents);
        if (! $image) {
            throw new MediaProcessingException('Invalid image data.');
        }

        $width = imagesx($image) ?: 1;
        $height = imagesy($image) ?: 1;
        [$newWidth, $newHeight] = $this->fitWithin($width, $height, $maxWidth, $maxHeight);

        $resized = imagecreatetruecolor($newWidth, $newHeight);

        if ($this->shouldKeepAlpha($format, $mime)) {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
            imagefill($resized, 0, 0, $transparent);
        }

        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        $this->ensureDirectory($destination);

        switch ($format) {
            case 'webp':
                if (! function_exists('imagewebp')) {
                    throw new MediaProcessingException('WEBP encoding is not available in GD.');
                }
                imagewebp($resized, $destination, $quality);
                break;
            case 'png':
                imagepng($resized, $destination, 6);
                break;
            default:
                imagejpeg($resized, $destination, $quality);
                break;
        }

        imagedestroy($resized);
        imagedestroy($image);

        return [$newWidth, $newHeight];
    }

    private function fitWithin(int $width, int $height, int $maxWidth, int $maxHeight): array
    {
        $width = max(1, $width);
        $height = max(1, $height);

        $ratio = min($maxWidth / $width, $maxHeight / $height, 1);

        $newWidth = (int) round($width * $ratio);
        $newHeight = (int) round($height * $ratio);

        return [max(1, $newWidth), max(1, $newHeight)];
    }

    private function decideImageFormat(string $preferred, bool $webpSupported, bool $hasAlpha): string
    {
        if ($hasAlpha && $preferred === 'webp' && $webpSupported) {
            return 'webp';
        }

        if ($hasAlpha) {
            return 'png';
        }

        if ($preferred === 'webp' && $webpSupported) {
            return 'webp';
        }

        return 'jpg';
    }

    private function mimeForFormat(string $format): string
    {
        return match ($format) {
            'webp' => 'image/webp',
            'png' => 'image/png',
            default => 'image/jpeg',
        };
    }

    private function ensureDirectory(string $absolutePath): void
    {
        $directory = pathinfo($absolutePath, PATHINFO_DIRNAME);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    private function webpSupported(): bool
    {
        if ($this->probe->imagickAvailable()) {
            try {
                $formats = (new \Imagick())->queryFormats('WEBP');

                return ! empty($formats);
            } catch (\Throwable) {
                return false;
            }
        }

        return function_exists('imagewebp');
    }

    private function imageHasAlpha(string $path, ?string $mime): bool
    {
        if ($this->probe->imagickAvailable()) {
            try {
                $image = new \Imagick($path);
                $hasAlpha = $image->getImageAlphaChannel();
                $image->clear();
                $image->destroy();

                return (bool) $hasAlpha;
            } catch (\Throwable) {
                // fall back to mime-based detection
            }
        }

        return in_array($mime, ['image/png', 'image/webp'], true);
    }

    private function shouldKeepAlpha(string $format, ?string $mime): bool
    {
        if ($format === 'png' || $format === 'webp') {
            return true;
        }

        return $mime === 'image/png';
    }

    private function tempFilePath(string $extension): string
    {
        $dir = storage_path('app/tmp/processed');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $extension = ltrim($extension, '.');
        $extension = $extension ? '.' . $extension : '';

        return $dir . '/' . uniqid('media_', true) . $extension;
    }
}
