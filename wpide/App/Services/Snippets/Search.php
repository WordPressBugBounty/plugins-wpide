<?php

namespace WPIDE\App\Services\Snippets;

/**
 * Value search for condition fields. Returns [['value' => string, 'label' => string], ...].
 * With $ids, returns labels for those values (to show saved selections).
 */
class Search
{
    public static function run(string $kind, string $term, array $ids = []): array
    {
        switch ($kind) {
            case 'posts':
                $args = ['post_type' => array_values(get_post_types(['public' => true])), 'post_status' => 'any', 'posts_per_page' => 20, 'orderby' => 'relevance'];
                $args += $ids ? ['post__in' => array_map('intval', $ids), 'orderby' => 'post__in'] : ['s' => $term];

                return array_map(function ($post) {
                    return ['value' => (string) $post->ID, 'label' => sprintf('%s (%s #%d)', $post->post_title ?: __('(no title)', 'wpide'), $post->post_type, $post->ID)];
                }, get_posts($args));

            case 'users':
                $args = ['number' => 20, 'fields' => ['ID', 'display_name', 'user_login']];
                $args += $ids ? ['include' => array_map('intval', $ids)] : ['search' => '*' . $term . '*', 'search_columns' => ['user_login', 'user_nicename', 'user_email', 'display_name']];

                return array_map(function ($user) {
                    return ['value' => (string) $user->ID, 'label' => $user->display_name . ' (' . $user->user_login . ')'];
                }, get_users($args));

            case 'terms':
                $args = ['taxonomy' => array_values(get_taxonomies(['public' => true])), 'hide_empty' => false, 'number' => 20];
                $args += $ids ? ['include' => array_map('intval', $ids)] : ['search' => $term];
                $terms = get_terms($args);

                return is_wp_error($terms) ? [] : array_map(function ($term) {
                    return ['value' => (string) $term->term_id, 'label' => $term->name . ' (' . $term->taxonomy . ')'];
                }, $terms);

            case 'snippets':
                $items = Repository::query(['status' => 'all', 'search' => $ids ? '' : $term, 'per_page' => $ids ? 500 : 20])['items'];

                if ($ids) {
                    $items = array_filter($items, function ($snippet) use ($ids) {
                        return in_array((string) $snippet['id'], $ids, true);
                    });
                }

                return array_values(array_map(function ($snippet) {
                    return ['value' => (string) $snippet['id'], 'label' => $snippet['title'] . ' (#' . $snippet['id'] . ')'];
                }, $items));

            case 'products':
                if (!function_exists('wc_get_products')) {
                    return [];
                }

                $products = $ids ? array_filter(array_map('wc_get_product', array_map('intval', $ids))) : wc_get_products(['limit' => 20, 's' => $term, 'type' => ['simple', 'variable', 'variation', 'grouped', 'external']]);

                return array_values(array_map(function ($product) {
                    $sku = $product->get_sku();

                    return ['value' => (string) $product->get_id(), 'label' => $product->get_name() . ($sku ? ' (SKU: ' . $sku . ')' : '')];
                }, $products));

            case 'memberships':
                if (!class_exists('MeprProduct')) {
                    return [];
                }

                $results = [];

                foreach (\MeprProduct::get_all() as $product) {
                    if (($ids && in_array((string) $product->ID, $ids, true)) || (!$ids && ($term === '' || stripos($product->post_title, $term) !== false))) {
                        $results[] = ['value' => (string) $product->ID, 'label' => $product->post_title];
                    }
                }

                return $results;

            case 'sites':
                if (!is_multisite()) {
                    return [];
                }

                $args = $ids ? ['site__in' => array_map('intval', $ids), 'number' => 50] : ['search' => $term, 'number' => 30];

                return array_map(function ($site) {
                    return ['value' => (string) $site->blog_id, 'label' => get_blog_option($site->blog_id, 'blogname') . ' (' . $site->domain . $site->path . ')'];
                }, get_sites($args));
        }

        return [];
    }
}
