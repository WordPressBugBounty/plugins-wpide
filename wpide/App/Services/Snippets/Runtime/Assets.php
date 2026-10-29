<?php

namespace WPIDE\App\Services\Snippets\Runtime;

/**
 * CSS and JavaScript snippets loaded as files.
 *
 * Files are named by content hash (cache-busting without query strings), written atomically on first use and
 * checked on every request; when the folder is not writable or a file disappears, output falls back to inline.
 */
class Assets
{
    public static function dir(): string
    {
        $uploads = wp_upload_dir(null, false);

        return wp_normalize_path(rtrim($uploads['basedir'], '/') . '/wpide-snippets/assets');
    }

    public static function baseUrl(): string
    {
        $uploads = wp_upload_dir(null, false);

        return rtrim(set_url_scheme($uploads['baseurl']), '/') . '/wpide-snippets/assets';
    }

    public static function url(array $snippet, string $extension, string $content): ?string
    {
        $name = (int) $snippet['id'] . '-' . substr(hash('sha256', $content), 0, 16) . '.' . $extension;
        $dir = self::dir();
        $file = $dir . '/' . $name;

        if (!is_file($file)) {
            if ((!is_dir($dir) && !wp_mkdir_p($dir)) || !is_writable($dir)) {
                return null;
            }

            $temp = $file . '.' . uniqid('', true) . '.tmp';

            if (@file_put_contents($temp, $content, LOCK_EX) === false || !@rename($temp, $file)) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
                @unlink($temp); // phpcs:ignore WordPress.PHP.NoSilencedErrors

                return null;
            }

            if (!file_exists($dir . '/index.php')) {
                @file_put_contents($dir . '/index.php', "<?php\n// Silence is golden.\n"); // phpcs:ignore WordPress.PHP.NoSilencedErrors
            }

            foreach ((array) glob($dir . '/' . (int) $snippet['id'] . '-*.' . $extension) as $old) {
                if ($old !== $file) {
                    @unlink($old); // phpcs:ignore WordPress.PHP.NoSilencedErrors
                }
            }
        }

        return self::baseUrl() . '/' . $name;
    }

    public static function purge(int $snippetId = 0)
    {
        foreach (['css', 'js'] as $extension) {
            foreach ((array) glob(self::dir() . '/' . ($snippetId ? $snippetId . '-' : '') . '*.' . $extension) as $file) {
                @unlink($file); // phpcs:ignore WordPress.PHP.NoSilencedErrors
            }
        }
    }
}
