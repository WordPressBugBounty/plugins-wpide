<?php

namespace WPIDE\App\Services\Snippets\Runtime;

use WPIDE\App\Services\Snippets\Plans;
use WPIDE\App\Services\Snippets\Registry\Types;

/**
 * Shortcodes: [wpide_snippet id="12"], custom names chosen per snippet, and [wpcode id="…"] for content
 * migrated from WPCode (only while WPCode itself is not active).
 *
 * Declared shortcode attributes become PHP variables in PHP and Universal snippets and {attr:name} placeholders
 * in other types. Inactive, unscheduled or non-matching snippets render nothing. As in WPCode Lite, the free
 * plan has [wpide_snippet] and attributes as PHP variables; custom names and {attr:name} placeholders are Pro.
 */
class Shortcodes
{
    const TAG = 'wpide_snippet';

    public static function register()
    {
        add_shortcode(self::TAG, [__CLASS__, 'render']);

        foreach (Plans::pro() ? Runtime::manifestSnippets() : [] as $id => $snippet) {
            if ($snippet['shortcode'] !== '' && !shortcode_exists($snippet['shortcode'])) {
                add_shortcode($snippet['shortcode'], function ($atts) use ($id) {
                    return self::render(array_merge((array) $atts, ['id' => $id]));
                });
            }
        }

        if (!function_exists('wpcode') && !shortcode_exists('wpcode')) {
            add_shortcode('wpcode', [__CLASS__, 'renderMigrated']);
        }
    }

    /**
     * While snippets are disabled, keep the shortcodes registered so raw tags are not printed.
     */
    public static function registerDisabled()
    {
        add_shortcode(self::TAG, '__return_empty_string');
    }

    public static function render($atts): string
    {
        $atts = (array) $atts;
        $id = isset($atts['id']) ? (int) $atts['id'] : 0;
        $snippets = Runtime::manifestSnippets();

        if (!$id || !isset($snippets[$id])) {
            return '';
        }

        $snippet = $snippets[$id];

        // Shortcodes honour schedule, device and conditions, but not the location context.
        $check = $snippet;
        $check['insert_method'] = 'shortcode';

        if (!Runtime::shouldRun($check)) {
            return '';
        }

        $declared = (array) ($snippet['shortcode_attributes'] ?? []);
        $values = shortcode_atts(array_fill_keys($declared, ''), $atts, $snippet['shortcode'] !== '' ? $snippet['shortcode'] : self::TAG);
        $values = array_map('sanitize_text_field', $values);

        if (Types::executesPhp($snippet['code_type'])) {
            return Runtime::renderTracked($snippet, 'shortcode', $values);
        }

        $output = Runtime::renderTracked($snippet, 'shortcode');

        foreach (Plans::pro() ? $values : [] as $name => $value) {
            $output = str_replace('{attr:' . $name . '}', esc_html($value), $output);
        }

        return $output;
    }

    public static function renderMigrated($atts): string
    {
        $atts = (array) $atts;
        $legacyId = isset($atts['id']) ? (int) $atts['id'] : 0;
        $map = \WPIDE\App\Services\Snippets\Manifest::get()['migrated'] ?? [];

        if (!$legacyId || !isset($map[$legacyId])) {
            return '';
        }

        $atts['id'] = $map[$legacyId];

        return self::render($atts);
    }
}
