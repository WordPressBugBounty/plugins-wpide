<?php

namespace WPIDE\App\Services\Snippets;

use WPIDE\App\Services\Snippets\Registry\Types;

/**
 * Smart tags: dynamic values in HTML, Text, Blocks, JavaScript, CSS and Universal snippets.
 *
 * Syntax: {tag}, {tag="parameter"} (e.g. {custom_field="price"}), with an optional modifier:
 * {tag|raw}, {tag|html}, {tag|attr}, {tag|url}, {tag|js}. Values are escaped for the snippet type by default
 * (HTML for markup, JavaScript string escaping for JS, tags stripped for CSS). Tags whose value is markup
 * (post content, price HTML) are filtered with wp_kses_post instead of escaped. Unknown tags are left as is.
 * Smart tags are Pro: as in WPCode Lite, the free plan lists them but leaves them as written.
 */
class SmartTags
{
    public static function groups(): array
    {
        return [
            'general' => __('General', 'wpide'),
            'user' => __('Current user', 'wpide'),
            'author' => __('Author', 'wpide'),
            'woocommerce' => 'WooCommerce',
            'edd' => 'Easy Digital Downloads',
            'seo' => __('SEO', 'wpide'),
            'memberpress' => 'MemberPress',
        ];
    }

    /**
     * Tag id => definition: label, group, callback(parameter), optional 'html' (value is markup), 'available'.
     */
    public static function all(): array
    {
        $tags = [
            // Content.
            'id' => ['label' => __('Content ID', 'wpide'), 'group' => 'general', 'callback' => function () {
                return (string) get_the_ID();
            }],
            'permalink' => ['label' => __('Permalink', 'wpide'), 'group' => 'general', 'callback' => function () {
                return (string) get_permalink();
            }],
            'title' => ['label' => __('Content title', 'wpide'), 'group' => 'general', 'callback' => function () {
                return (string) get_the_title();
            }],
            'excerpt' => ['label' => __('Excerpt', 'wpide'), 'group' => 'general', 'callback' => function () {
                return get_the_ID() ? wp_strip_all_tags(get_the_excerpt()) : '';
            }],
            'content' => ['label' => __('Content', 'wpide'), 'group' => 'general', 'html' => true, 'callback' => function () {
                $post = get_post();

                return $post ? (string) $post->post_content : '';
            }],
            'featured_image' => ['label' => __('Featured image URL', 'wpide'), 'group' => 'general', 'callback' => function () {
                return (string) get_the_post_thumbnail_url(null, 'full');
            }],
            'categories' => ['label' => __('Categories (comma separated)', 'wpide'), 'group' => 'general', 'callback' => function () {
                return wp_strip_all_tags((string) get_the_category_list(','));
            }],
            'tags' => ['label' => __('Tags (comma separated)', 'wpide'), 'group' => 'general', 'callback' => function () {
                return wp_strip_all_tags((string) get_the_tag_list('', ','));
            }],
            'date_published' => ['label' => __('Publish date', 'wpide'), 'group' => 'general', 'callback' => function () {
                return (string) get_the_date();
            }],
            'date_modified' => ['label' => __('Last modified date', 'wpide'), 'group' => 'general', 'callback' => function () {
                return (string) get_the_modified_date();
            }],
            'post_type' => ['label' => __('Post type', 'wpide'), 'group' => 'general', 'callback' => function () {
                return (string) get_post_type();
            }],
            'custom_field' => ['label' => __('Custom field', 'wpide'), 'group' => 'general', 'parameter' => 'meta_key', 'callback' => function ($key) {
                $id = get_the_ID();
                $value = $id && $key !== '' ? get_post_meta($id, $key, true) : '';

                return is_scalar($value) ? (string) $value : '';
            }],
            'site_name' => ['label' => __('Site name', 'wpide'), 'group' => 'general', 'callback' => function () {
                return (string) get_bloginfo('name');
            }],
            'site_url' => ['label' => __('Site URL', 'wpide'), 'group' => 'general', 'callback' => function () {
                return home_url('/');
            }],
            'login_url' => ['label' => __('Login URL', 'wpide'), 'group' => 'general', 'callback' => function () {
                return wp_login_url();
            }],
            'logout_url' => ['label' => __('Logout URL', 'wpide'), 'group' => 'general', 'callback' => function () {
                return wp_logout_url();
            }],

            // Current user.
            'email' => ['label' => __('User email', 'wpide'), 'group' => 'user', 'callback' => function () {
                return (string) wp_get_current_user()->user_email;
            }],
            'first_name' => ['label' => __('User first name', 'wpide'), 'group' => 'user', 'callback' => function () {
                return (string) wp_get_current_user()->first_name;
            }],
            'last_name' => ['label' => __('User last name', 'wpide'), 'group' => 'user', 'callback' => function () {
                return (string) wp_get_current_user()->last_name;
            }],

            // Author.
            'author_id' => ['label' => __('Author ID', 'wpide'), 'group' => 'author', 'callback' => function () {
                $post = get_post();

                return $post ? (string) $post->post_author : '';
            }],
            'author_name' => ['label' => __('Author name', 'wpide'), 'group' => 'author', 'callback' => function () {
                $post = get_post();

                return $post ? (string) get_the_author_meta('display_name', $post->post_author) : '';
            }],
            'author_url' => ['label' => __('Author URL', 'wpide'), 'group' => 'author', 'callback' => function () {
                $post = get_post();

                return $post ? (string) get_author_posts_url((int) $post->post_author) : '';
            }],
            'author_bio' => ['label' => __('Author bio', 'wpide'), 'group' => 'author', 'callback' => function () {
                $post = get_post();

                return $post ? (string) get_the_author_meta('description', $post->post_author) : '';
            }],
        ];

        $tags += self::wooTags();
        $tags += self::eddTags();
        $tags += self::seoTags();
        $tags += self::memberpressTags();

        return apply_filters('wpide_snippets_smart_tags', $tags);
    }

    protected static function wooProduct()
    {
        if (!function_exists('wc_get_product')) {
            return null;
        }

        global $product;

        if (is_object($product) && $product instanceof \WC_Product) {
            return $product;
        }

        $id = get_the_ID();

        return $id && get_post_type($id) === 'product' ? wc_get_product($id) : null;
    }

    protected static function wooOrder()
    {
        global $wp;

        if (!function_exists('wc_get_order') || empty($wp->query_vars['order-received'])) {
            return null;
        }

        $order = wc_get_order(absint($wp->query_vars['order-received']));

        return $order ?: null;
    }

    protected static function wooTags(): array
    {
        $available = function () {
            return class_exists('WooCommerce');
        };
        $product = function (callable $value, bool $html = false) use ($available) {
            return ['group' => 'woocommerce', 'available' => $available, 'html' => $html, 'callback' => function () use ($value) {
                $item = self::wooProduct();

                return $item ? (string) $value($item) : '';
            }];
        };
        $order = function (callable $value) use ($available) {
            return ['group' => 'woocommerce', 'available' => $available, 'callback' => function () use ($value) {
                $item = self::wooOrder();

                return $item ? (string) $value($item) : '';
            }];
        };
        $terms = function ($item, $taxonomy) {
            $names = wp_get_post_terms($item->get_id(), $taxonomy, ['fields' => 'names']);

            return is_wp_error($names) ? '' : implode(', ', $names);
        };

        return [
            'wc_order_number' => ['label' => __('Order number', 'wpide')] + $order(function ($o) {
                return $o->get_order_number();
            }),
            'wc_order_subtotal' => ['label' => __('Order subtotal', 'wpide')] + $order(function ($o) {
                return $o->get_subtotal();
            }),
            'wc_order_total' => ['label' => __('Order total', 'wpide')] + $order(function ($o) {
                return $o->get_total();
            }),
            'wc_product_id' => ['label' => __('Product ID', 'wpide')] + $product(function ($p) {
                return $p->get_id();
            }),
            'wc_product_permalink' => ['label' => __('Product URL', 'wpide')] + $product(function ($p) {
                return $p->get_permalink();
            }),
            'wc_product_title' => ['label' => __('Product title', 'wpide')] + $product(function ($p) {
                return $p->get_name();
            }),
            'wc_product_sku' => ['label' => __('Product SKU', 'wpide')] + $product(function ($p) {
                return $p->get_sku();
            }),
            'wc_product_price' => ['label' => __('Regular price', 'wpide')] + $product(function ($p) {
                return $p->get_regular_price();
            }),
            'wc_product_sale_price' => ['label' => __('Sale price', 'wpide')] + $product(function ($p) {
                return $p->get_sale_price();
            }),
            'wc_product_price_html' => ['label' => __('Price with currency', 'wpide')] + $product(function ($p) {
                return $p->get_price_html();
            }, true),
            'wc_product_in_stock' => ['label' => __('In stock (yes/no)', 'wpide')] + $product(function ($p) {
                return $p->is_in_stock() ? __('yes', 'wpide') : __('no', 'wpide');
            }),
            'wc_product_stock_quantity' => ['label' => __('Stock quantity', 'wpide')] + $product(function ($p) {
                return (string) $p->get_stock_quantity();
            }),
            'wc_product_description' => ['label' => __('Product description', 'wpide')] + $product(function ($p) {
                return $p->get_description();
            }, true),
            'wc_product_short_description' => ['label' => __('Product short description', 'wpide')] + $product(function ($p) {
                return $p->get_short_description();
            }, true),
            'wc_product_image' => ['label' => __('Product image URL', 'wpide')] + $product(function ($p) {
                return (string) wp_get_attachment_image_url($p->get_image_id(), 'full');
            }),
            'wc_product_category' => ['label' => __('Product categories', 'wpide')] + $product(function ($p) use ($terms) {
                return $terms($p, 'product_cat');
            }),
            'wc_product_tags' => ['label' => __('Product tags', 'wpide')] + $product(function ($p) use ($terms) {
                return $terms($p, 'product_tag');
            }),
            'wc_product_rating' => ['label' => __('Average rating', 'wpide')] + $product(function ($p) {
                return $p->get_average_rating();
            }),
            'wc_product_review_count' => ['label' => __('Review count', 'wpide')] + $product(function ($p) {
                return $p->get_review_count();
            }),
            'wc_product_gallery' => ['label' => __('Gallery image URLs', 'wpide')] + $product(function ($p) {
                return implode(',', array_filter(array_map(function ($id) {
                    return wp_get_attachment_image_url($id, 'full');
                }, $p->get_gallery_image_ids())));
            }),
        ];
    }

    protected static function eddTags(): array
    {
        $download = function (callable $value) {
            return ['group' => 'edd', 'available' => function () {
                return class_exists('Easy_Digital_Downloads');
            }, 'callback' => function () use ($value) {
                $id = get_the_ID();

                return $id && get_post_type($id) === 'download' && function_exists('edd_get_download') ? (string) $value($id) : '';
            }];
        };

        return [
            'edd_file_name' => ['label' => __('Download name', 'wpide')] + $download(function ($id) {
                return get_the_title($id);
            }),
            'edd_file_url' => ['label' => __('Download URL', 'wpide')] + $download(function ($id) {
                return get_permalink($id);
            }),
            'edd_file_price' => ['label' => __('Download price', 'wpide')] + $download(function ($id) {
                return function_exists('edd_get_download_price') ? edd_get_download_price($id) : '';
            }),
            'edd_file_notes' => ['label' => __('Download notes', 'wpide')] + $download(function ($id) {
                return function_exists('edd_get_product_notes') ? wp_strip_all_tags(edd_get_product_notes($id)) : '';
            }),
        ];
    }

    protected static function seoTags(): array
    {
        $yoast = function () {
            return defined('WPSEO_VERSION');
        };
        $aioseo = function () {
            return function_exists('aioseo');
        };

        return [
            'yoast_seo_title' => ['label' => __('Yoast SEO title', 'wpide'), 'group' => 'seo', 'available' => $yoast, 'callback' => function () {
                return function_exists('YoastSEO') ? (string) YoastSEO()->meta->for_current_page()->title : '';
            }],
            'yoast_seo_description' => ['label' => __('Yoast SEO description', 'wpide'), 'group' => 'seo', 'available' => $yoast, 'callback' => function () {
                return function_exists('YoastSEO') ? (string) YoastSEO()->meta->for_current_page()->description : '';
            }],
            'aioseo_title' => ['label' => __('AIOSEO title', 'wpide'), 'group' => 'seo', 'available' => $aioseo, 'callback' => function () {
                return function_exists('aioseo') && isset(aioseo()->meta->title) ? (string) aioseo()->meta->title->getTitle() : '';
            }],
            'aioseo_description' => ['label' => __('AIOSEO description', 'wpide'), 'group' => 'seo', 'available' => $aioseo, 'callback' => function () {
                return function_exists('aioseo') && isset(aioseo()->meta->description) ? (string) aioseo()->meta->description->getDescription() : '';
            }],
        ];
    }

    protected static function memberpressTags(): array
    {
        $available = function () {
            return defined('MEPR_VERSION');
        };
        $course = function (callable $value) use ($available) {
            return ['group' => 'memberpress', 'available' => $available, 'callback' => function () use ($value) {
                $post = get_post();

                return $post && $post->post_type === 'mpcs-course' ? (string) $value($post) : '';
            }];
        };
        $membership = function (callable $value) use ($available) {
            return ['group' => 'memberpress', 'available' => $available, 'callback' => function () use ($value) {
                $post = get_post();

                if (!$post || $post->post_type !== 'memberpressproduct' || !class_exists('MeprProduct')) {
                    return '';
                }

                return (string) $value(new \MeprProduct($post->ID), $post);
            }];
        };

        return [
            'mepr_course_name' => ['label' => __('Course name', 'wpide')] + $course(function ($p) {
                return get_the_title($p);
            }),
            'mepr_course_description' => ['label' => __('Course description', 'wpide')] + $course(function ($p) {
                return wp_strip_all_tags(get_the_excerpt($p));
            }),
            'mepr_course_url' => ['label' => __('Course URL', 'wpide')] + $course(function ($p) {
                return get_permalink($p);
            }),
            'mepr_course_price' => ['label' => __('Course price', 'wpide')] + $course(function ($p) {
                return (string) get_post_meta($p->ID, '_mpcs_course_price', true);
            }),
            'mepr_course_stock' => ['label' => __('Course availability', 'wpide')] + $course(function ($p) {
                return (string) get_post_meta($p->ID, '_mpcs_course_status', true);
            }),
            'mepr_course_instructor' => ['label' => __('Course instructor', 'wpide')] + $course(function ($p) {
                return (string) get_the_author_meta('display_name', $p->post_author);
            }),
            'mepr_name' => ['label' => __('Membership name', 'wpide')] + $membership(function ($m, $p) {
                return get_the_title($p);
            }),
            'mepr_price' => ['label' => __('Membership price', 'wpide')] + $membership(function ($m) {
                return isset($m->price) ? $m->price : '';
            }),
            'mepr_description' => ['label' => __('Membership description', 'wpide')] + $membership(function ($m, $p) {
                return wp_strip_all_tags(get_the_excerpt($p));
            }),
            'mepr_url' => ['label' => __('Membership URL', 'wpide')] + $membership(function ($m) {
                return method_exists($m, 'url') ? $m->url() : '';
            }),
            'mepr_billing_type' => ['label' => __('Membership billing type', 'wpide')] + $membership(function ($m) {
                return isset($m->period_type) && $m->period_type === 'lifetime' ? __('One-time', 'wpide') : __('Recurring', 'wpide');
            }),
            'mepr_period_type' => ['label' => __('Membership period type', 'wpide')] + $membership(function ($m) {
                return isset($m->period_type) ? $m->period_type : '';
            }),
            'mepr_period' => ['label' => __('Membership period', 'wpide')] + $membership(function ($m) {
                return isset($m->period) ? $m->period : '';
            }),
        ];
    }

    /**
     * Tags for the editor picker.
     */
    public static function forUi(): array
    {
        $list = [];

        foreach (self::all() as $id => $tag) {
            $available = isset($tag['available']) && is_callable($tag['available']) ? (bool) call_user_func($tag['available']) : true;
            $list[] = [
                'id' => $id,
                'label' => $tag['label'],
                'group' => $tag['group'],
                'available' => $available,
                'code' => !empty($tag['parameter']) ? '{' . $id . '="' . $tag['parameter'] . '"}' : '{' . $id . '}',
            ];
        }

        return $list;
    }

    /**
     * Replace smart tags in output. $raw skips escaping (used for files where escaping is applied by the type).
     */
    public static function replace(string $output, array $snippet = [], bool $raw = false): string
    {
        if (strpos($output, '{') === false || !Plans::pro() || (isset($snippet['settings']['smart_tags']) && !$snippet['settings']['smart_tags']) || !apply_filters('wpide_snippets_smart_tags_enabled', true, $snippet)) {
            return $output;
        }

        $tags = self::all();
        $type = $snippet['code_type'] ?? Types::HTML;

        return preg_replace_callback('/\{([a-z0-9_]+)(?:="([^"}]*)")?(?:\|(raw|html|attr|url|js))?\}/', function ($m) use ($tags, $type, $raw) {
            $id = $m[1];

            if (!isset($tags[$id])) {
                return $m[0];
            }

            $tag = $tags[$id];

            try {
                $value = (string) call_user_func($tag['callback'], isset($m[2]) ? sanitize_text_field($m[2]) : '');
            } catch (\Throwable $e) {
                return '';
            }

            $modifier = $m[3] ?? '';

            if ($modifier === '' && $raw) {
                $modifier = 'raw';
            }

            return self::escape($value, $modifier, $type, !empty($tag['html']));
        }, $output);
    }

    protected static function escape(string $value, string $modifier, string $type, bool $isHtml): string
    {
        switch ($modifier) {
            case 'raw':
                return $value;
            case 'attr':
                return esc_attr($value);
            case 'url':
                return esc_url($value);
            case 'js':
                return esc_js($value);
            case 'html':
                return esc_html($value);
        }

        if ($type === Types::JS) {
            return esc_js($value);
        }

        if (in_array($type, [Types::CSS, Types::SCSS], true)) {
            return preg_replace('/[^A-Za-z0-9 #%.,_\-\/:()"\']/', '', wp_strip_all_tags($value));
        }

        return $isHtml ? wp_kses_post($value) : esc_html($value);
    }
}
