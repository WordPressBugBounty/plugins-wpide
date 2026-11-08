<?php

namespace WPIDE\App\Services\Snippets\Runtime;

use WPIDE\App\Services\Snippets\Registry\Types;

/**
 * Generated PHP files for PHP and Universal snippets.
 *
 * Files are named <snippet id>-<content hash>.php, written atomically, and guarded against direct web
 * access (ABSPATH check, index.php and .htaccess). Line numbers match the snippet code.
 */
class PhpFiles
{
    const GUARD = "defined('ABSPATH') || exit;";

    public static function dir(): string
    {
        $uploads = wp_upload_dir(null, false);

        return wp_normalize_path(rtrim($uploads['basedir'], '/') . '/wpide-snippets/php');
    }

    /**
     * Source code for the snippet, for a file (with opening tag and guard) or for eval.
     */
    public static function source(array $snippet, bool $forFile = true): string
    {
        $code = (string) $snippet['code'];

        if ($snippet['code_type'] === Types::UNIVERSAL) {
            return $forFile ? '<?php ' . self::GUARD . ' ?>' . $code : '?>' . $code;
        }

        $code = preg_replace('/^\s*<\?php/i', '', $code, 1);

        if (!$forFile) {
            return $code;
        }

        // declare() and namespace must stay the first statements.
        if (preg_match('/^(\s*(?:declare\s*\([^)]*\)\s*;\s*)*(?:namespace\s+[A-Za-z0-9_\\\\]+\s*;)?)/', $code, $m) && trim($m[1]) !== '') {
            return '<?php ' . $m[1] . ' ' . self::GUARD . ' ' . substr($code, strlen($m[1]));
        }

        return '<?php ' . self::GUARD . ' ' . $code;
    }

    /**
     * Path of the generated file, creating it when needed. Null when files cannot be written.
     */
    public static function path(array $snippet): ?string
    {
        if (!apply_filters('wpide_snippets_php_files', true)) {
            return null;
        }

        $source = self::source($snippet, true);
        $dir = self::dir();
        $file = $dir . '/' . (int) $snippet['id'] . '-' . substr(hash('sha256', $source), 0, 20) . '.php';

        if (is_file($file)) {
            return $file;
        }

        if (!self::ensureDir($dir)) {
            return null;
        }

        $temp = $file . '.' . uniqid('', true) . '.tmp';

        if (@file_put_contents($temp, $source, LOCK_EX) === false || !@rename($temp, $file)) { // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions.rename_rename -- atomic replace; WP_Filesystem::move() is not atomic.
            wp_delete_file($temp);

            return null;
        }

        // Remove previous versions of this snippet.
        foreach ((array) glob($dir . '/' . (int) $snippet['id'] . '-*.php') as $old) {
            if ($old !== $file) {
                wp_delete_file($old);
            }
        }

        return $file;
    }

    public static function ensureDir(string $dir): bool
    {
        if (!is_dir($dir) && !wp_mkdir_p($dir)) {
            return false;
        }

        if (!wp_is_writable($dir)) {
            return false;
        }

        $parent = dirname($dir);

        foreach ([$parent, $dir] as $path) {
            if (!file_exists($path . '/index.php')) {
                @file_put_contents($path . '/index.php', "<?php\n// Silence is golden.\n"); // phpcs:ignore WordPress.PHP.NoSilencedErrors
            }
        }

        if (!file_exists($dir . '/.htaccess')) {
            @file_put_contents($dir . '/.htaccess', "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n"); // phpcs:ignore WordPress.PHP.NoSilencedErrors
        }

        return true;
    }

    public static function purge(int $snippetId = 0)
    {
        $pattern = self::dir() . '/' . ($snippetId ? $snippetId . '-' : '') . '*.php';

        foreach ((array) glob($pattern) as $file) {
            if (basename($file) !== 'index.php') {
                wp_delete_file($file);
            }
        }
    }
}
