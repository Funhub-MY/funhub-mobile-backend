<?php

if (! function_exists('storage_is_cloud')) {
    function storage_is_cloud(): bool
    {
        return in_array(
            config('filesystems.default'),
            config('storage.cloud_disks', ['hwc_obs', 's3']),
            true
        );
    }
}

if (! function_exists('storage_public_disk')) {
    function storage_public_disk(): string
    {
        $default = config('filesystems.default');

        return config("storage.public_disk_map.{$default}", $default);
    }
}

if (! function_exists('storage_private_disk')) {
    function storage_private_disk(): string
    {
        return config('filesystems.default');
    }
}

if (! function_exists('storage_resolve_disk')) {
    /**
     * Map legacy s3 disk names from the database to the canonical Huawei disk.
     */
    function storage_resolve_disk(string $disk): string
    {
        return config("storage.legacy_disk_aliases.{$disk}", $disk);
    }
}

if (! function_exists('storage_disk_url')) {
    function storage_disk_url(string $disk, string $path): string
    {
        return storage_rewrite_url(
            \Illuminate\Support\Facades\Storage::disk(storage_resolve_disk($disk))->url($path)
        ) ?? '';
    }
}

if (! function_exists('storage_use_admin_proxy')) {
    /**
     * Serve cloud media via the app origin in Filament admin to avoid OBS bucket CORS errors.
     */
    function storage_use_admin_proxy(): bool
    {
        if (app()->runningInConsole()) {
            return false;
        }

        $adminPrefix = trim(config('filament.path', 'admin'), '/');

        return request()->is($adminPrefix, $adminPrefix . '/*', 'livewire/*');
    }
}

if (! function_exists('storage_admin_media_url')) {
    function storage_admin_media_url(\App\Models\Media $media, string $conversionName = ''): string
    {
        $parameters = ['media' => $media->getKey()];

        if ($conversionName !== '') {
            $parameters['conversion'] = $conversionName;
        }

        return route('filament.storage.media', $parameters);
    }
}

if (! function_exists('storage_normalize_endpoint')) {
    /**
     * AWS SDK / Flysystem require endpoint to be a full URI (https://...).
     */
    function storage_normalize_endpoint(?string $endpoint): ?string
    {
        if ($endpoint === null || $endpoint === '') {
            return $endpoint;
        }

        $endpoint = trim($endpoint);

        if (preg_match('#^https?://#i', $endpoint)) {
            return rtrim($endpoint, '/');
        }

        return 'https://' . ltrim($endpoint, '/');
    }
}

if (! function_exists('storage_normalize_url')) {
    /**
     * Ensure storage URLs are absolute (browsers treat host-only values as relative paths).
     */
    function storage_normalize_url(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return $url;
        }

        $url = trim($url);

        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        // Already a root-relative app path (e.g. /storage/foo.jpg)
        if (str_starts_with($url, '/')) {
            return $url;
        }

        return 'https://' . ltrim($url, '/');
    }
}

if (! function_exists('storage_rewrite_url')) {
    /**
     * Rewrite legacy AWS / CloudFront URLs to the Huawei public base at read time.
     */
    function storage_rewrite_url(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return $url;
        }

        $replaceTo = storage_normalize_url(config('storage.url_replace_to'));

        foreach (config('storage.url_replace_from', []) as $replaceFrom) {
            if ($replaceFrom !== '' && str_starts_with($url, $replaceFrom)) {
                return storage_normalize_url($replaceTo . substr($url, strlen($replaceFrom)));
            }
        }

        if ($replaceTo === null || $replaceTo === '') {
            return storage_normalize_url($url);
        }

        foreach (config('storage.legacy_url_hosts', []) as $hostFragment) {
            if ($hostFragment === '' || ! str_contains($url, $hostFragment)) {
                continue;
            }

            $path = parse_url($url, PHP_URL_PATH) ?: '';
            $query = parse_url($url, PHP_URL_QUERY);
            $rewritten = $replaceTo . $path;

            if ($query) {
                $rewritten .= '?' . $query;
            }

            return storage_normalize_url($rewritten);
        }

        return storage_normalize_url($url);
    }
}

if (! function_exists('storage_rewrite_text')) {
    /**
     * Rewrite legacy storage URLs embedded in HTML or plain text (e.g. article body).
     */
    function storage_rewrite_text(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        $replaceTo = storage_normalize_url(config('storage.url_replace_to'));

        if ($replaceTo === null || $replaceTo === '') {
            return $text;
        }

        $updated = $text;

        foreach (config('storage.url_replace_from', []) as $replaceFrom) {
            if ($replaceFrom !== '') {
                $updated = str_replace($replaceFrom, $replaceTo, $updated);
            }
        }

        foreach (config('storage.legacy_url_hosts', []) as $hostFragment) {
            if ($hostFragment === '') {
                continue;
            }

            $updated = preg_replace(
                '#https?://[^"\s<>]*' . preg_quote($hostFragment, '#') . '[^"\s<>]*(/[^"\s<>]*)#i',
                $replaceTo . '$1',
                $updated
            ) ?? $updated;
        }

        return $updated;
    }
}
