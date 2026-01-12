<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaService
{
    public function disk(): string
    {
        return config('media.disk', 'public');
    }

    public function url(?string $path): ?string
    {
        if (!$path) return null;
        return Storage::disk($this->disk())->url($path);
    }

    public function storeImage(UploadedFile $file, string $folder): string
    {
        $folder = trim($folder, '/');
        $root = trim(config('media.root_folder', 'uploads'), '/');

        // ✅ Optimise (resize + compression) en gardant le format
        $optimized = $this->optimizeImageKeepFormat($file);

        $extOriginal = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $ext = $optimized['ext'] ?? $extOriginal;

        $name = Str::uuid()->toString() . '.' . $ext;
        $path = "{$root}/{$folder}/{$name}";

        if (!empty($optimized['binary'])) {
            Storage::disk($this->disk())->put($path, $optimized['binary'], 'public');
        } else {
            // fallback (rare) : stocke l’original
            Storage::disk($this->disk())->putFileAs(dirname($path), $file, basename($path));
        }

        return $path;
    }

    public function replaceImage(?string $oldPath, UploadedFile $file, string $folder): string
    {
        $newPath = $this->storeImage($file, $folder);

        if ($oldPath) {
            Storage::disk($this->disk())->delete($oldPath);
        }

        return $newPath;
    }

    public function delete(?string $path): void
    {
        if (!$path) return;
        Storage::disk($this->disk())->delete($path);
    }

    /**
     * Optimise en conservant le format d’origine :
     * - JPG => imagejpeg (quality)
     * - PNG => imagepng (compression level)
     * - WEBP => imagewebp (quality)
     * - GIF => si animé: Imagick si dispo, sinon fallback (ne pas casser l’animation)
     */
    private function optimizeImageKeepFormat(UploadedFile $file): ?array
    {
        $maxW = (int) config('media.images.max_width', 1600);
        $maxH = (int) config('media.images.max_height', 1600);

        $jpegQuality = (int) config('media.images.jpeg_quality', 75);
        $webpQuality = (int) config('media.images.webp_quality', 75);
        $pngLevel = (int) config('media.images.png_compression', 6);

        $compressAnimatedGif = (bool) config('media.images.compress_animated_gif', true);

        $realPath = $file->getRealPath();
        if (!$realPath || !is_file($realPath)) return null;

        $mime = strtolower((string) $file->getMimeType());
        $ext  = strtolower((string) ($file->getClientOriginalExtension() ?: ''));

        // Harmoniser ext si besoin
        if (!$ext) {
            $ext = match (true) {
                str_contains($mime, 'jpeg') => 'jpg',
                str_contains($mime, 'png')  => 'png',
                str_contains($mime, 'webp') => 'webp',
                str_contains($mime, 'gif')  => 'gif',
                default => 'jpg',
            };
        }

        // ===== GIF animé => Imagick si dispo (sinon on n’optimise pas pour ne pas casser l’animation)
        if ($ext === 'gif' || str_contains($mime, 'gif')) {
            $isAnimated = $this->isAnimatedGif($realPath);

            if ($isAnimated) {
                if ($compressAnimatedGif && class_exists(\Imagick::class)) {
                    return $this->optimizeAnimatedGifWithImagick($realPath, $maxW, $maxH);
                }

                // Fallback : on garde l’original (sinon tu perds l’animation avec GD)
                return null;
            }

            // GIF non animé => GD OK
            $img = @imagecreatefromgif($realPath);
            if (!$img) return null;

            [$img] = $this->resizeIfNeeded($img, $maxW, $maxH);

            ob_start();
            imagegif($img);
            $binary = ob_get_clean();
            imagedestroy($img);

            return ['binary' => $binary, 'ext' => 'gif'];
        }

        // ===== GD (JPG/PNG/WEBP)
        $img = $this->createGdImageFromFile($realPath, $mime, $ext);
        if (!$img) return null;

        // EXIF rotate pour JPG
        $img = $this->applyExifOrientationIfNeeded($img, $realPath, $mime, $ext);

        [$img] = $this->resizeIfNeeded($img, $maxW, $maxH);

        // Encode en gardant le format
        ob_start();
        if ($ext === 'png') {
            // PNG: compression 0-9 (lossless)
            imagepng($img, null, max(0, min(9, $pngLevel)));
        } elseif ($ext === 'webp' && function_exists('imagewebp')) {
            imagewebp($img, null, max(1, min(100, $webpQuality)));
        } else {
            // jpg/jpeg
            imagejpeg($img, null, max(1, min(100, $jpegQuality)));
            $ext = 'jpg'; // normaliser
        }
        $binary = ob_get_clean();

        imagedestroy($img);

        if (!$binary) return null;

        return ['binary' => $binary, 'ext' => $ext];
    }

    private function resizeIfNeeded($img, int $maxW, int $maxH): array
    {
        $w = imagesx($img);
        $h = imagesy($img);

        if ($w <= 0 || $h <= 0) return [$img];

        $ratio = min($maxW / $w, $maxH / $h, 1);
        $nw = (int) round($w * $ratio);
        $nh = (int) round($h * $ratio);

        if ($nw === $w && $nh === $h) return [$img];

        $dst = imagecreatetruecolor($nw, $nh);

        // préserver alpha
        imagealphablending($dst, false);
        imagesavealpha($dst, true);

        imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);

        imagedestroy($img);

        return [$dst];
    }

    private function createGdImageFromFile(string $path, string $mime, string $ext)
    {
        try {
            if (str_contains($mime, 'jpeg') || $ext === 'jpg' || $ext === 'jpeg') return @imagecreatefromjpeg($path);
            if (str_contains($mime, 'png')  || $ext === 'png')  return @imagecreatefrompng($path);
            if ((str_contains($mime, 'webp') || $ext === 'webp') && function_exists('imagecreatefromwebp')) {
                return @imagecreatefromwebp($path);
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    private function applyExifOrientationIfNeeded($img, string $path, string $mime, string $ext)
    {
        $isJpeg = str_contains($mime, 'jpeg') || $ext === 'jpg' || $ext === 'jpeg';
        if (!$isJpeg) return $img;
        if (!function_exists('exif_read_data')) return $img;

        try {
            $exif = @exif_read_data($path);
            $orientation = $exif['Orientation'] ?? null;

            return match ($orientation) {
                3 => imagerotate($img, 180, 0),
                6 => imagerotate($img, -90, 0),
                8 => imagerotate($img, 90, 0),
                default => $img,
            };
        } catch (\Throwable $e) {
            return $img;
        }
    }

    private function isAnimatedGif(string $path): bool
    {
        // Détection simple du nombre de frames
        $contents = @file_get_contents($path);
        if ($contents === false) return false;

        // pattern frames GIF
        return preg_match('/\x00\x21\xF9\x04.{4}\x00\x2C/s', $contents) > 1;
    }

    private function optimizeAnimatedGifWithImagick(string $path, int $maxW, int $maxH): ?array
    {
        try {
            $gif = new \Imagick($path);
            $gif = $gif->coalesceImages();

            foreach ($gif as $frame) {
                $frame->thumbnailImage($maxW, $maxH, true);
                $frame->setImageFormat('gif');
            }

            $gif = $gif->deconstructImages();
            $binary = $gif->getImagesBlob();

            return ['binary' => $binary, 'ext' => 'gif'];
        } catch (\Throwable $e) {
            return null;
        }
    }
}
