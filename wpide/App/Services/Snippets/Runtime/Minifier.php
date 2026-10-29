<?php

namespace WPIDE\App\Services\Snippets\Runtime;

use WPIDE\App\Services\Snippets\Registry\Types;

/**
 * Conservative output compression.
 *
 * CSS is fully minified. JavaScript loses comments, indentation and blank lines but keeps line breaks, so code
 * relying on automatic semicolon insertion keeps working. HTML loses comments (not conditional comments) and
 * whitespace between tags; <pre>, <textarea>, <script> and <style> contents are preserved.
 */
class Minifier
{
    public static function minify(string $output, string $type): string
    {
        switch ($type) {
            case Types::CSS:
            case Types::SCSS:
                return preg_replace_callback('#(<style[^>]*>)(.*?)(</style>)#is', function ($m) {
                    return $m[1] . self::css($m[2]) . $m[3];
                }, $output);
            case Types::JS:
                return preg_replace_callback('#(<script[^>]*>)(.*?)(</script>)#is', function ($m) {
                    return $m[1] . self::js($m[2]) . $m[3];
                }, $output);
            case Types::HTML:
                return self::html($output);
        }

        return $output;
    }

    public static function css(string $css): string
    {
        $css = preg_replace('#/\*.*?\*/#s', '', $css);
        $css = preg_replace('/\s+/', ' ', $css);
        $css = preg_replace('/\s*([{}:;,>~])\s*/', '$1', $css);
        $css = str_replace(';}', '}', $css);

        return trim($css);
    }

    public static function js(string $js): string
    {
        $out = '';
        $length = strlen($js);
        $quote = null;

        for ($i = 0; $i < $length; $i++) {
            $char = $js[$i];
            $next = $i + 1 < $length ? $js[$i + 1] : '';

            if ($quote !== null) {
                $out .= $char;

                if ($char === '\\' && $next !== '') {
                    $out .= $next;
                    $i++;
                } elseif ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === '"' || $char === "'" || $char === '`') {
                $quote = $char;
                $out .= $char;
                continue;
            }

            if ($char === '/' && $next === '*') {
                $end = strpos($js, '*/', $i + 2);
                $i = $end === false ? $length : $end + 1;
                continue;
            }

            // Line comment: only when it cannot be part of a URL in a regex or division context.
            if ($char === '/' && $next === '/' && ($i === 0 || !in_array($js[$i - 1], [':', '\\'], true))) {
                $end = strpos($js, "\n", $i);
                $i = $end === false ? $length : $end - 1;
                continue;
            }

            $out .= $char;
        }

        $lines = array_filter(array_map('trim', explode("\n", $out)), 'strlen');

        return implode("\n", $lines);
    }

    public static function html(string $html): string
    {
        $preserved = [];

        $html = preg_replace_callback('#<(pre|textarea|script|style)\b[^>]*>.*?</\1>#is', function ($m) use (&$preserved) {
            $key = '%%WPIDE_PRESERVE_' . count($preserved) . '%%';
            $preserved[$key] = $m[0];

            return $key;
        }, $html);

        $html = preg_replace('/<!--(?!\[if).*?-->/s', '', $html);
        $html = preg_replace('/>\s+</', '><', $html);
        $html = preg_replace('/\s{2,}/', ' ', $html);

        return strtr(trim($html), $preserved);
    }
}
