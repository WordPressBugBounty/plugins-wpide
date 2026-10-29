<?php

namespace WPIDE\App\Services\Snippets\Runtime;

use WPIDE\App\Services\Snippets\Plans;
use WPIDE\App\Services\Snippets\Registry\Types;
use WPIDE\App\Services\Snippets\SmartTags;

/**
 * Renders a single snippet according to its type.
 *
 * PHP and Universal code run from generated, content-hashed files (uploads/wpide-snippets/php), so a
 * failure inside a later callback still points at the snippet; when the folder is not writable the code
 * runs through eval. Each run is isolated: a Throwable stops that snippet only.
 */
class Executor
{
    /**
     * @return string Output (empty for PHP snippets that only register hooks).
     */
    public static function render(array $snippet, string $phase = 'output', array $attributes = [], bool $activation = false): string
    {
        $type = $snippet['code_type'];

        // SCSS and Blocks snippets need WPIDE Pro (as in WPCode Lite).
        if (Types::isLocked($type)) {
            return '';
        }

        if (Types::executesPhp($type)) {
            $output = self::runPhp($snippet, $phase, $attributes, $activation);

            if ($type === Types::UNIVERSAL && $output !== '') {
                $output = SmartTags::replace($output, $snippet);
            }

            return (string) apply_filters('wpide_snippets_output', $output, $snippet, $phase);
        }

        $code = (string) $snippet['code'];

        switch ($type) {
            case Types::TEXT:
                $output = apply_filters('wpide_snippets_text_shortcodes', true, $snippet) ? do_shortcode($code) : $code;
                break;
            case Types::BLOCKS:
                $output = function_exists('do_blocks') ? do_blocks($code) : $code;
                break;
            case Types::CSS:
            case Types::SCSS:
                $output = self::styleTag($snippet);
                break;
            case Types::JS:
                $output = self::scriptTag($snippet);
                break;
            default:
                $output = $code;
        }

        if (in_array($type, [Types::HTML, Types::TEXT, Types::BLOCKS, Types::JS, Types::CSS, Types::SCSS], true)) {
            $output = SmartTags::replace($output, $snippet);
        }

        if (!empty($snippet['settings']['compress_output']) && Plans::pro() && in_array($type, [Types::HTML, Types::JS, Types::CSS, Types::SCSS], true)) {
            $output = Minifier::minify($output, $type);
        }

        return (string) apply_filters('wpide_snippets_output', $output, $snippet, $phase);
    }

    protected static function css(array $snippet): string
    {
        return $snippet['code_type'] === Types::SCSS ? (string) $snippet['compiled_code'] : (string) $snippet['code'];
    }

    protected static function styleTag(array $snippet): string
    {
        $css = self::css($snippet);

        if (trim($css) === '') {
            return '';
        }

        if (!empty($snippet['settings']['load_as_file']) && Plans::pro()) {
            $url = Assets::url($snippet, 'css', SmartTags::replace($css, $snippet, true));

            if ($url) {
                return sprintf("<link rel='stylesheet' id='wpide-snippet-%d-css' href='%s' media='all' />\n", (int) $snippet['id'], esc_url($url));
            }
        }

        return sprintf("<style id=\"wpide-snippet-%d\">%s</style>\n", (int) $snippet['id'], $css);
    }

    protected static function scriptTag(array $snippet): string
    {
        $js = (string) $snippet['code'];

        if (trim($js) === '') {
            return '';
        }

        if (!empty($snippet['settings']['load_as_file']) && Plans::pro()) {
            $url = Assets::url($snippet, 'js', SmartTags::replace($js, $snippet, true));

            if ($url) {
                return sprintf("<script id=\"wpide-snippet-%d-js\" src=\"%s\"></script>\n", (int) $snippet['id'], esc_url($url));
            }
        }

        return sprintf("<script id=\"wpide-snippet-%d\">%s</script>\n", (int) $snippet['id'], $js);
    }

    protected static function runPhp(array $snippet, string $phase, array $attributes, bool $activation): string
    {
        $id = (int) $snippet['id'];
        $level = ob_get_level();

        Guard::enter($snippet, $phase, $activation);
        ob_start();

        try {
            $file = PhpFiles::path($snippet);

            if ($file) {
                Guard::registerFile($file, $id);
                self::includeFile($file, $attributes);
            } else {
                self::evaluate(PhpFiles::source($snippet, false), $attributes);
            }

            $output = (string) ob_get_clean();
        } catch (\Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }

            Guard::leave();
            Guard::caught($id, $e, $phase, $activation);

            if ($activation) {
                throw $e;
            }

            return '';
        }

        Guard::leave();

        return $output;
    }

    /**
     * Isolated scope: shortcode attributes become variables, nothing else leaks in.
     */
    protected static function includeFile(string $__file, array $__attributes)
    {
        extract($__attributes, EXTR_SKIP); // phpcs:ignore WordPress.PHP.DontExtract

        include $__file;
    }

    protected static function evaluate(string $__code, array $__attributes)
    {
        extract($__attributes, EXTR_SKIP); // phpcs:ignore WordPress.PHP.DontExtract

        eval($__code); // phpcs:ignore Squiz.PHP.Eval
    }
}
