<?php

namespace App\Support;

class LocalImageSourceResolver
{
    /**
     * Resolve an image URL/path to a validated local file without performing
     * any network I/O.
     *
     * @param mixed $source
     */
    public function resolve($source): ?string
    {
        if (! is_string($source)) {
            return null;
        }

        $source = trim($source);

        if ($source === ''
            || strlen($source) > (int) config('imagecache.max_source_length', 2048)
            || str_contains($source, "\0")) {
            return null;
        }

        $path = $this->sourcePath($source);

        if ($path === null) {
            return null;
        }

        $candidate = realpath(public_path(ltrim($path, '/')));

        if ($candidate === false || ! is_file($candidate) || ! is_readable($candidate)) {
            return null;
        }

        if (! $this->isInsideAllowedRoot($candidate)) {
            return null;
        }

        $size = filesize($candidate);

        if ($size === false || $size > (int) config('imagecache.max_source_bytes', 8 * 1024 * 1024)) {
            return null;
        }

        $dimensions = @getimagesize($candidate);

        if (! is_array($dimensions)) {
            return null;
        }

        $width = (int) ($dimensions[0] ?? 0);
        $height = (int) ($dimensions[1] ?? 0);
        $mime = strtolower((string) ($dimensions['mime'] ?? ''));
        $allowedMimeTypes = config('imagecache.allowed_mime_types', [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
        ]);

        if ($width < 1
            || $height < 1
            || $width > (int) config('imagecache.max_source_dimension', 8000)
            || $height > (int) config('imagecache.max_source_dimension', 8000)
            || ($width * $height) > (int) config('imagecache.max_source_pixels', 12000000)
            || ! in_array($mime, $allowedMimeTypes, true)) {
            return null;
        }

        return $candidate;
    }


    private function sourcePath(string $source): ?string
    {
        $parts = parse_url($source);

        if ($parts === false) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($scheme !== '' || $host !== '') {
            if (! in_array($scheme, ['http', 'https'], true)
                || $host === ''
                || ! $this->isAllowedHost($host)
                || isset($parts['user'])
                || isset($parts['pass'])) {
                return null;
            }
        }

        $path = rawurldecode((string) ($parts['path'] ?? ''));

        if ($path === '' || str_contains($path, "\0") || str_contains($path, '\\')) {
            return null;
        }

        return '/' . ltrim($path, '/');
    }


    private function isAllowedHost(string $host): bool
    {
        $hosts = collect([
            parse_url((string) config('app.url'), PHP_URL_HOST),
            parse_url((string) config('settings.images_domain'), PHP_URL_HOST),
            request()->getHost(),
        ])->filter()
            ->map(fn ($allowedHost) => strtolower((string) $allowedHost))
            ->unique();

        return $hosts->contains(strtolower($host));
    }


    private function isInsideAllowedRoot(string $candidate): bool
    {
        $roots = config('imagecache.source_roots', [
            public_path(),
            storage_path('app/public'),
        ]);

        foreach ($roots as $root) {
            $realRoot = realpath((string) $root);

            if ($realRoot === false) {
                continue;
            }

            $prefix = rtrim($realRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

            if (str_starts_with($candidate, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
