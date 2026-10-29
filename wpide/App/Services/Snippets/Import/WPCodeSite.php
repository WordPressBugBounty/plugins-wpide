<?php

namespace WPIDE\App\Services\Snippets\Import;

use WPIDE\App\Services\Snippets\GlobalScripts;
use WPIDE\App\Services\Snippets\PageScripts;
use WPIDE\App\Services\Snippets\Plans;
use WPIDE\App\Services\Snippets\Repository;
use WPIDE\App\Services\Snippets\SpecialFiles;

/**
 * Migration from WPCode installed on this site. Reads WPCode's own records (posts, meta and block content),
 * so settings the JSON export leaves out are kept: schedule, device type, file loading, compression, generator
 * settings. WPCode data is left untouched, so WPCode can keep running until you switch it off.
 * Also offers WPCode's global header/body/footer scripts, page scripts and ads.txt / app-ads.txt /
 * service-worker.js / robots.txt content.
 */
class WPCodeSite extends Importer
{
    use WPCodeMapping;

    public function id(): string
    {
        return 'wpcode-site';
    }

    public function label(): string
    {
        return __('WPCode on this site', 'wpide');
    }

    public function description(): string
    {
        return __('Copy snippets and settings from WPCode installed on this site, including settings missing from WPCode export files.', 'wpide');
    }

    public function available(): bool
    {
        global $wpdb;

        return (bool) $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'wpcode' AND post_status IN ('publish','draft') LIMIT 1");
    }

    protected function items(?array $file = null): array
    {
        $posts = get_posts(['post_type' => 'wpcode', 'post_status' => ['publish', 'draft'], 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC', 'suppress_filters' => true]);
        $items = [];

        foreach ($posts as $post) {
            $meta = function ($key, $default = null) use ($post) {
                $value = get_post_meta($post->ID, $key, true);

                return $value === '' ? $default : $value;
            };

            $type = $this->term($post->ID, 'wpcode_type');
            $location = $this->term($post->ID, 'wpcode_location');
            $code = $post->post_content;

            if ($type === 'blocks') {
                $blockPost = absint($meta('_wpcode_block_editor_id', 0));
                $code = $blockPost ? (string) get_post_field('post_content', $blockPost) : '';
            }

            $schedule = (array) $meta('_wpcode_schedule', []);
            $source = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'code' => $code,
                'code_type' => $type,
                'location' => $location,
                'auto_insert' => (int) $meta('_wpcode_auto_insert', $location !== '' ? 1 : 0),
                'insert_number' => (int) $meta('_wpcode_auto_insert_number', 1),
                'use_rules' => (bool) $meta('_wpcode_conditional_logic_enabled', false),
                'rules' => $meta('_wpcode_conditional_logic', []),
                'priority' => (int) $meta('_wpcode_priority', 10),
                'location_extra' => $meta('_wpcode_location_extra', ''),
                'shortcode_attributes' => (array) $meta('_wpcode_shortcode_attributes', []),
                'compiled_code' => (string) $meta('_wpcode_compiled_code', ''),
                'note' => (string) $meta('_wpcode_note', ''),
                'custom_shortcode' => (string) $meta('_wpcode_custom_shortcode', ''),
                'tags' => wp_get_object_terms($post->ID, 'wpcode_tags', ['fields' => 'slugs']),
            ];

            list($snippet, $preserved, $unsupported) = $this->mapWPCode($source, [
                'schedule' => ['start' => (string) ($schedule['start'] ?? ''), 'end' => (string) ($schedule['end'] ?? '')],
                'device_type' => in_array($meta('_wpcode_device_type', 'any'), ['any', 'desktop', 'mobile'], true) ? $meta('_wpcode_device_type', 'any') : 'any',
                'settings' => ['load_as_file' => (bool) $meta('_wpcode_load_as_file', false), 'compress_output' => (bool) $meta('_wpcode_compress_output', false)],
                'generator' => (string) $meta('_wpcode_generator', ''),
                'generator_data' => (array) $meta('_wpcode_generator_data', []),
            ]);

            $items[] = [
                'key' => (string) $post->ID,
                'title' => $snippet['title'],
                'code_type' => $snippet['code_type'],
                'snippet' => $snippet,
                'original_status' => $post->post_status === 'publish' ? __('active', 'wpide') : __('inactive', 'wpide'),
                'preserved' => array_merge($preserved, ['schedule', 'device_type', 'load_as_file', 'compress_output', 'generator']),
                'missing' => [],
                'unsupported' => $unsupported,
                'warnings' => $post->post_status === 'publish' ? [__('Active in WPCode. It is imported inactive: deactivate it in WPCode before activating it here, or it will run twice.', 'wpide')] : [],
            ];
        }

        return $items;
    }

    protected function term(int $postId, string $taxonomy): string
    {
        $terms = wp_get_object_terms($postId, $taxonomy, ['fields' => 'slugs']);

        return is_array($terms) && $terms ? (string) $terms[0] : '';
    }

    public function extras(?array $file = null): array
    {
        $extras = [];
        $global = [];

        foreach (GlobalScripts::AREAS as $area) {
            $value = (string) get_option('ihaf_insert_' . $area, '');

            if (trim($value) !== '') {
                $global[$area] = strlen($value);
            }
        }

        if ($global) {
            $extras[] = ['key' => 'global_scripts', 'label' => __('Global header, body and footer scripts', 'wpide'), 'detail' => implode(', ', array_keys($global))];
        }

        $files = ['robotstxt' => 'robots.txt', 'adstxt' => 'ads.txt', 'appadstxt' => 'app-ads.txt', 'serviceworkerjs' => 'service-worker.js'];
        $found = [];

        foreach ($files as $key => $name) {
            $value = get_option('wpcode_file_' . $key);

            if (is_array($value) && !empty($value['content'])) {
                $found[] = $name;
            }
        }

        // Special files and page scripts are Pro.
        if ($found && Plans::pro()) {
            $extras[] = ['key' => 'special_files', 'label' => __('Special files', 'wpide'), 'detail' => implode(', ', $found)];
        }

        global $wpdb;
        $pages = (int) $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key IN ('_wpcode_header_scripts','_wpcode_body_scripts','_wpcode_footer_scripts','_wpcode_page_snippets')");

        if ($pages && Plans::pro()) {
            $extras[] = ['key' => 'page_scripts', 'label' => __('Page scripts', 'wpide'), 'detail' => sprintf(_n('%d page', '%d pages', $pages, 'wpide'), $pages)];
        }

        return $extras;
    }

    public function commitExtras(array $selected, ?array $file = null): array
    {
        $report = [];

        if (in_array('global_scripts', $selected, true)) {
            $current = GlobalScripts::get();
            $merged = [];

            foreach (GlobalScripts::AREAS as $area) {
                $incoming = (string) get_option('ihaf_insert_' . $area, '');
                $merged[$area] = trim($current[$area]) === '' ? $incoming : $current[$area] . ($incoming !== '' ? "\n" . $incoming : '');
            }

            GlobalScripts::save($merged);
            $report[] = __('Global scripts imported (appended to any existing WPIDE global scripts).', 'wpide');
        }

        if (in_array('special_files', $selected, true) && Plans::pro()) {
            foreach (['robotstxt' => 'robots.txt', 'adstxt' => 'ads.txt', 'appadstxt' => 'app-ads.txt', 'serviceworkerjs' => 'service-worker.js'] as $key => $name) {
                $value = get_option('wpcode_file_' . $key);

                if (is_array($value) && !empty($value['content'])) {
                    SpecialFiles::save($name, (string) $value['content'], false);
                }
            }

            $report[] = __('Special files imported (disabled until you enable them, to avoid serving them twice).', 'wpide');
        }

        if (in_array('page_scripts', $selected, true) && Plans::pro()) {
            $report[] = $this->importPageScripts();
        }

        return $report;
    }

    protected function importPageScripts(): string
    {
        global $wpdb;

        $map = [];

        foreach (Repository::query(['status' => 'all', 'per_page' => 500])['items'] as $snippet) {
            if (preg_match('/^wpcode:(\d+)$/', $snippet['source_ref'], $m)) {
                $map[(int) $m[1]] = $snippet['id'];
            }
        }

        $postIds = $wpdb->get_col("SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ('_wpcode_header_scripts','_wpcode_body_scripts','_wpcode_footer_scripts','_wpcode_page_snippets')");
        $count = 0;
        $locations = array_flip(PageScripts::PAGE_SNIPPET_LOCATIONS);

        foreach ($postIds as $postId) {
            $value = PageScripts::get((int) $postId);

            foreach (GlobalScripts::AREAS as $area) {
                $source = get_post_meta((int) $postId, '_wpcode_' . $area . '_scripts', true);

                if (is_array($source)) {
                    foreach (['any', 'desktop', 'mobile'] as $variant) {
                        $value[$area][$variant] = trim($value[$area][$variant] . "\n" . (string) ($source[$variant] ?? ''));
                    }

                    $value['disable_global'][$area] = !empty($source['disable_global']);
                }
            }

            foreach ((array) get_post_meta((int) $postId, '_wpcode_page_snippets', true) as $row) {
                $legacy = (int) ($row['snippet_id'] ?? 0);

                if (isset($map[$legacy]) && isset($locations[$row['location'] ?? ''])) {
                    $value['snippets'][] = ['id' => $map[$legacy], 'location' => $row['location']];
                }
            }

            update_post_meta((int) $postId, PageScripts::META, $value);
            $count++;
        }

        return sprintf(_n('Page scripts imported for %d page.', 'Page scripts imported for %d pages.', $count, 'wpide'), $count);
    }
}
