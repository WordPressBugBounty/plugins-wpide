<?php

namespace WPIDE\App\Services\Snippets\Registry;

use WPIDE\App\Services\Snippets\Plans;

/**
 * Insertion locations.
 *
 * IDs match the WPCode reference for imports. Each location declares its group, the code types it accepts,
 * the request context it runs in, an optional WordPress hook (with priority) and the extra settings it needs.
 */
class Locations
{
    const PHP_ONLY = ['php'];
    const OUTPUT_TYPES = ['php', 'universal', 'html', 'text', 'blocks', 'css', 'scss', 'js'];

    /**
     * Groups WPCode Lite shows with a PRO badge (CSS selector, word count and share of the content, stores and
     * memberships), plus WPIDE's custom action hook.
     */
    const PRO_GROUPS = ['anywhere', 'content', 'custom', 'woocommerce', 'edd', 'memberpress'];

    public static function groups(): array
    {
        return [
            'everywhere' => __('PHP execution', 'wpide'),
            'site_wide' => __('Site wide', 'wpide'),
            'single' => __('Single posts and pages', 'wpide'),
            'content' => __('Inside post content', 'wpide'),
            'archive' => __('Archives and post lists', 'wpide'),
            'admin' => __('Admin area', 'wpide'),
            'anywhere' => __('Anywhere on the page (CSS selector)', 'wpide'),
            'custom' => __('Custom hook', 'wpide'),
            'woocommerce' => __('WooCommerce', 'wpide'),
            'edd' => __('Easy Digital Downloads', 'wpide'),
            'memberpress' => __('MemberPress', 'wpide'),
        ];
    }

    /**
     * Runtime data without labels (safe before init): group, types, context, hook/filter, priority, extras.
     */
    public static function runtime(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $locations = [
            'everywhere' => ['group' => 'everywhere', 'types' => self::PHP_ONLY, 'context' => 'all'],
            'frontend_only' => ['group' => 'everywhere', 'types' => self::PHP_ONLY, 'context' => 'frontend'],
            'admin_only' => ['group' => 'everywhere', 'types' => self::PHP_ONLY, 'context' => 'admin'],
            'frontend_cl' => ['group' => 'everywhere', 'types' => self::PHP_ONLY, 'context' => 'frontend'],
            'on_demand' => ['group' => 'everywhere', 'types' => self::PHP_ONLY, 'context' => 'manual'],
            'site_wide_header' => ['group' => 'site_wide', 'hook' => 'wp_head', 'context' => 'frontend'],
            'site_wide_body' => ['group' => 'site_wide', 'hook' => 'wp_body_open', 'context' => 'frontend'],
            'site_wide_footer' => ['group' => 'site_wide', 'hook' => 'wp_footer', 'context' => 'frontend'],
            'before_post' => ['group' => 'single', 'context' => 'frontend'],
            'after_post' => ['group' => 'single', 'context' => 'frontend'],
            'before_content' => ['group' => 'single', 'context' => 'frontend'],
            'after_content' => ['group' => 'single', 'context' => 'frontend'],
            'before_paragraph' => ['group' => 'single', 'context' => 'frontend', 'number' => true],
            'after_paragraph' => ['group' => 'single', 'context' => 'frontend', 'number' => true],
            'after_words' => ['group' => 'content', 'context' => 'frontend', 'number' => true],
            'every_words' => ['group' => 'content', 'context' => 'frontend', 'number' => true],
            'content_quarter' => ['group' => 'content', 'context' => 'frontend', 'fraction' => 0.25],
            'content_one_third' => ['group' => 'content', 'context' => 'frontend', 'fraction' => 1 / 3],
            'content_half' => ['group' => 'content', 'context' => 'frontend', 'fraction' => 0.5],
            'content_two_thirds' => ['group' => 'content', 'context' => 'frontend', 'fraction' => 2 / 3],
            'content_quarter_third' => ['group' => 'content', 'context' => 'frontend', 'fraction' => 0.75],
            'content_80' => ['group' => 'content', 'context' => 'frontend', 'fraction' => 0.8],
            'before_excerpt' => ['group' => 'archive', 'context' => 'frontend'],
            'after_excerpt' => ['group' => 'archive', 'context' => 'frontend'],
            'between_posts' => ['group' => 'archive', 'context' => 'frontend'],
            'archive_before_post' => ['group' => 'archive', 'context' => 'frontend', 'number' => true],
            'archive_after_post' => ['group' => 'archive', 'context' => 'frontend', 'number' => true],
            'admin_head' => ['group' => 'admin', 'hook' => 'admin_head', 'priority' => 9, 'context' => 'admin'],
            'admin_footer' => ['group' => 'admin', 'hook' => 'admin_footer', 'priority' => 9, 'context' => 'admin'],
            'before_css_selector' => ['group' => 'anywhere', 'context' => 'frontend', 'selector' => 'before'],
            'after_css_selector' => ['group' => 'anywhere', 'context' => 'frontend', 'selector' => 'after'],
            'start_css_selector' => ['group' => 'anywhere', 'context' => 'frontend', 'selector' => 'start'],
            'end_css_selector' => ['group' => 'anywhere', 'context' => 'frontend', 'selector' => 'end'],
            'replace_css_selector' => ['group' => 'anywhere', 'context' => 'frontend', 'selector' => 'replace'],
            'custom_hook' => ['group' => 'custom', 'context' => 'all', 'custom_hook' => true],
            'wc_before_products_list' => ['group' => 'woocommerce', 'hook' => 'woocommerce_before_shop_loop', 'context' => 'frontend', 'plugin' => 'woocommerce'],
            'wc_after_products_list' => ['group' => 'woocommerce', 'hook' => 'woocommerce_after_shop_loop', 'context' => 'frontend', 'plugin' => 'woocommerce'],
            'wc_before_single_product' => ['group' => 'woocommerce', 'hook' => 'woocommerce_before_single_product', 'context' => 'frontend', 'plugin' => 'woocommerce'],
            'wc_after_single_product' => ['group' => 'woocommerce', 'hook' => 'woocommerce_after_single_product', 'context' => 'frontend', 'plugin' => 'woocommerce'],
            'wc_before_single_product_summary' => ['group' => 'woocommerce', 'hook' => 'woocommerce_before_single_product_summary', 'context' => 'frontend', 'plugin' => 'woocommerce'],
            'wc_after_single_product_summary' => ['group' => 'woocommerce', 'hook' => 'woocommerce_after_single_product_summary', 'context' => 'frontend', 'plugin' => 'woocommerce'],
            'woocommerce_before_cart' => ['group' => 'woocommerce', 'hook' => 'woocommerce_before_cart', 'context' => 'frontend', 'plugin' => 'woocommerce'],
            'woocommerce_after_cart' => ['group' => 'woocommerce', 'hook' => 'woocommerce_after_cart', 'context' => 'frontend', 'plugin' => 'woocommerce'],
            'woocommerce_before_checkout_form' => ['group' => 'woocommerce', 'hook' => 'woocommerce_before_checkout_form', 'context' => 'frontend', 'plugin' => 'woocommerce'],
            'woocommerce_after_checkout_form' => ['group' => 'woocommerce', 'hook' => 'woocommerce_after_checkout_form', 'context' => 'frontend', 'plugin' => 'woocommerce'],
            'woocommerce_checkout_order_review_19' => ['group' => 'woocommerce', 'hook' => 'woocommerce_checkout_order_review', 'priority' => 19, 'context' => 'frontend', 'plugin' => 'woocommerce'],
            'woocommerce_checkout_order_review_21' => ['group' => 'woocommerce', 'hook' => 'woocommerce_checkout_order_review', 'priority' => 21, 'context' => 'frontend', 'plugin' => 'woocommerce'],
            'woocommerce_before_thankyou' => ['group' => 'woocommerce', 'hook' => 'woocommerce_before_thankyou', 'context' => 'frontend', 'plugin' => 'woocommerce'],
            'edd_purchase_link_top' => ['group' => 'edd', 'hook' => 'edd_purchase_link_top', 'context' => 'frontend', 'plugin' => 'edd'],
            'edd_purchase_link_end' => ['group' => 'edd', 'hook' => 'edd_purchase_link_end', 'context' => 'frontend', 'plugin' => 'edd'],
            'edd_before_download_content' => ['group' => 'edd', 'hook' => 'edd_before_download_content', 'context' => 'frontend', 'plugin' => 'edd'],
            'edd_after_download_content' => ['group' => 'edd', 'hook' => 'edd_after_download_content', 'context' => 'frontend', 'plugin' => 'edd'],
            'edd_before_cart' => ['group' => 'edd', 'hook' => 'edd_before_cart', 'context' => 'frontend', 'plugin' => 'edd'],
            'edd_after_cart' => ['group' => 'edd', 'hook' => 'edd_after_cart', 'context' => 'frontend', 'plugin' => 'edd'],
            'edd_before_checkout_cart' => ['group' => 'edd', 'hook' => 'edd_before_checkout_cart', 'context' => 'frontend', 'plugin' => 'edd'],
            'edd_after_checkout_cart' => ['group' => 'edd', 'hook' => 'edd_after_checkout_cart', 'context' => 'frontend', 'plugin' => 'edd'],
            'edd_before_purchase_form' => ['group' => 'edd', 'hook' => 'edd_before_purchase_form', 'context' => 'frontend', 'plugin' => 'edd'],
            'edd_after_purchase_form' => ['group' => 'edd', 'hook' => 'edd_after_purchase_form', 'context' => 'frontend', 'plugin' => 'edd'],
            'mepr-above-checkout-form' => ['group' => 'memberpress', 'hook' => 'mepr-above-checkout-form', 'context' => 'frontend', 'plugin' => 'memberpress'],
            'mepr-checkout-before-submit' => ['group' => 'memberpress', 'hook' => 'mepr-checkout-before-submit', 'context' => 'frontend', 'plugin' => 'memberpress'],
            'mepr-checkout-before-coupon-field' => ['group' => 'memberpress', 'hook' => 'mepr-checkout-before-coupon-field', 'context' => 'frontend', 'plugin' => 'memberpress'],
            'mepr-account-home-before-name' => ['group' => 'memberpress', 'hook' => 'mepr-account-home-before-name', 'context' => 'frontend', 'plugin' => 'memberpress'],
            'mepr_before_account_subscriptions' => ['group' => 'memberpress', 'hook' => 'mepr_before_account_subscriptions', 'context' => 'frontend', 'plugin' => 'memberpress'],
            'mepr-login-form-before-submit' => ['group' => 'memberpress', 'hook' => 'mepr-login-form-before-submit', 'context' => 'frontend', 'plugin' => 'memberpress'],
            'mepr_unauthorized_message_before' => ['group' => 'memberpress', 'filter' => 'mepr-unauthorized-message', 'position' => 'before', 'context' => 'frontend', 'plugin' => 'memberpress'],
            'mepr_unauthorized_message_after' => ['group' => 'memberpress', 'filter' => 'mepr-unauthorized-message', 'position' => 'after', 'context' => 'frontend', 'plugin' => 'memberpress'],
        ];

        foreach ($locations as $id => &$location) {
            $location['id'] = $id;
            $location += [
                'types' => self::OUTPUT_TYPES,
                'priority' => 10,
            ];
        }

        return $cache = apply_filters('wpide_snippets_locations', $locations);
    }

    /**
     * Labels and descriptions for the admin UI.
     */
    public static function labels(): array
    {
        return [
            'everywhere' => ['label' => __('Run everywhere', 'wpide'), 'description' => __('Runs early on every request: frontend, admin, AJAX, REST, cron and CLI.', 'wpide')],
            'frontend_only' => ['label' => __('Frontend only', 'wpide'), 'description' => __('Runs early on public pages only.', 'wpide')],
            'admin_only' => ['label' => __('Admin only', 'wpide'), 'description' => __('Runs early in wp-admin only.', 'wpide')],
            'frontend_cl' => ['label' => __('Frontend, after the query (conditions)', 'wpide'), 'description' => __('Runs on public pages once WordPress knows which page is shown, so page conditions can be used.', 'wpide')],
            'on_demand' => ['label' => __('On demand', 'wpide'), 'description' => __('Never runs automatically. Run it from the editor, WP-CLI or wpide_run_snippet().', 'wpide')],
            'site_wide_header' => ['label' => __('Site wide header', 'wpide'), 'description' => __('Printed in <head> on every public page.', 'wpide')],
            'site_wide_body' => ['label' => __('Site wide body', 'wpide'), 'description' => __('Printed right after the opening <body> tag (theme must call wp_body_open).', 'wpide')],
            'site_wide_footer' => ['label' => __('Site wide footer', 'wpide'), 'description' => __('Printed before </body> on every public page.', 'wpide')],
            'before_post' => ['label' => __('Before post', 'wpide'), 'description' => __('Before the post on single posts and pages.', 'wpide')],
            'after_post' => ['label' => __('After post', 'wpide'), 'description' => __('After the post on single posts and pages.', 'wpide')],
            'before_content' => ['label' => __('Before content', 'wpide'), 'description' => __('At the start of the post content.', 'wpide')],
            'after_content' => ['label' => __('After content', 'wpide'), 'description' => __('At the end of the post content.', 'wpide')],
            'before_paragraph' => ['label' => __('Before paragraph #', 'wpide'), 'description' => __('Before the chosen paragraph of the content.', 'wpide'), 'number_label' => __('Paragraph number', 'wpide')],
            'after_paragraph' => ['label' => __('After paragraph #', 'wpide'), 'description' => __('After the chosen paragraph of the content.', 'wpide'), 'number_label' => __('Paragraph number', 'wpide')],
            'after_words' => ['label' => __('After # words', 'wpide'), 'description' => __('After the paragraph that reaches the chosen word count.', 'wpide'), 'number_label' => __('Number of words', 'wpide')],
            'every_words' => ['label' => __('Every # words', 'wpide'), 'description' => __('Repeated after each block of the chosen word count, at paragraph boundaries.', 'wpide'), 'number_label' => __('Number of words', 'wpide')],
            'content_quarter' => ['label' => __('After 25% of the content', 'wpide'), 'description' => __('At the paragraph boundary closest to the first quarter.', 'wpide')],
            'content_one_third' => ['label' => __('After 33% of the content', 'wpide'), 'description' => __('At the paragraph boundary closest to the first third.', 'wpide')],
            'content_half' => ['label' => __('In the middle of the content', 'wpide'), 'description' => __('At the paragraph boundary closest to the middle.', 'wpide')],
            'content_two_thirds' => ['label' => __('After 66% of the content', 'wpide'), 'description' => __('At the paragraph boundary closest to two thirds.', 'wpide')],
            'content_quarter_third' => ['label' => __('After 75% of the content', 'wpide'), 'description' => __('At the paragraph boundary closest to three quarters.', 'wpide')],
            'content_80' => ['label' => __('After 80% of the content', 'wpide'), 'description' => __('At the paragraph boundary closest to 80%.', 'wpide')],
            'before_excerpt' => ['label' => __('Before excerpt', 'wpide'), 'description' => __('Before each post excerpt in lists.', 'wpide')],
            'after_excerpt' => ['label' => __('After excerpt', 'wpide'), 'description' => __('After each post excerpt in lists.', 'wpide')],
            'between_posts' => ['label' => __('Between posts', 'wpide'), 'description' => __('Between the posts of a list.', 'wpide')],
            'archive_before_post' => ['label' => __('Before post # in a list', 'wpide'), 'description' => __('Before the chosen post of a list.', 'wpide'), 'number_label' => __('Post number', 'wpide')],
            'archive_after_post' => ['label' => __('After post # in a list', 'wpide'), 'description' => __('After the chosen post of a list.', 'wpide'), 'number_label' => __('Post number', 'wpide')],
            'admin_head' => ['label' => __('Admin header', 'wpide'), 'description' => __('Printed in <head> on wp-admin screens.', 'wpide')],
            'admin_footer' => ['label' => __('Admin footer', 'wpide'), 'description' => __('Printed in the footer of wp-admin screens.', 'wpide')],
            'before_css_selector' => ['label' => __('Before element', 'wpide'), 'description' => __('Before the element matching a CSS selector.', 'wpide')],
            'after_css_selector' => ['label' => __('After element', 'wpide'), 'description' => __('After the element matching a CSS selector.', 'wpide')],
            'start_css_selector' => ['label' => __('At the start of element', 'wpide'), 'description' => __('Inside the matching element, before its content.', 'wpide')],
            'end_css_selector' => ['label' => __('At the end of element', 'wpide'), 'description' => __('Inside the matching element, after its content.', 'wpide')],
            'replace_css_selector' => ['label' => __('Replace element', 'wpide'), 'description' => __('Replaces the element matching a CSS selector.', 'wpide')],
            'custom_hook' => ['label' => __('Custom action hook', 'wpide'), 'description' => __('Runs PHP, or prints the output, when the WordPress action you name fires (theme, plugin or your own do_action), at the snippet priority.', 'wpide')],
            'wc_before_products_list' => ['label' => __('Before the product list', 'wpide')],
            'wc_after_products_list' => ['label' => __('After the product list', 'wpide')],
            'wc_before_single_product' => ['label' => __('Before a single product', 'wpide')],
            'wc_after_single_product' => ['label' => __('After a single product', 'wpide')],
            'wc_before_single_product_summary' => ['label' => __('Before the product summary', 'wpide')],
            'wc_after_single_product_summary' => ['label' => __('After the product summary', 'wpide')],
            'woocommerce_before_cart' => ['label' => __('Before the cart', 'wpide')],
            'woocommerce_after_cart' => ['label' => __('After the cart', 'wpide')],
            'woocommerce_before_checkout_form' => ['label' => __('Before the checkout form', 'wpide')],
            'woocommerce_after_checkout_form' => ['label' => __('After the checkout form', 'wpide')],
            'woocommerce_checkout_order_review_19' => ['label' => __('Before the payment button', 'wpide')],
            'woocommerce_checkout_order_review_21' => ['label' => __('After the payment button', 'wpide')],
            'woocommerce_before_thankyou' => ['label' => __('Before the thank-you page', 'wpide')],
            'edd_purchase_link_top' => ['label' => __('Before the purchase button', 'wpide')],
            'edd_purchase_link_end' => ['label' => __('After the purchase button', 'wpide')],
            'edd_before_download_content' => ['label' => __('Before a single download', 'wpide')],
            'edd_after_download_content' => ['label' => __('After a single download', 'wpide')],
            'edd_before_cart' => ['label' => __('Before the cart', 'wpide')],
            'edd_after_cart' => ['label' => __('After the cart', 'wpide')],
            'edd_before_checkout_cart' => ['label' => __('Before the checkout cart', 'wpide')],
            'edd_after_checkout_cart' => ['label' => __('After the checkout cart', 'wpide')],
            'edd_before_purchase_form' => ['label' => __('Before the checkout form', 'wpide')],
            'edd_after_purchase_form' => ['label' => __('After the checkout form', 'wpide')],
            'mepr-above-checkout-form' => ['label' => __('Before the registration form', 'wpide')],
            'mepr-checkout-before-submit' => ['label' => __('Before the checkout submit button', 'wpide')],
            'mepr-checkout-before-coupon-field' => ['label' => __('Before the coupon field', 'wpide')],
            'mepr-account-home-before-name' => ['label' => __('Before the account first name', 'wpide')],
            'mepr_before_account_subscriptions' => ['label' => __('Before the subscriptions list', 'wpide')],
            'mepr-login-form-before-submit' => ['label' => __('Before the login submit button', 'wpide')],
            'mepr_unauthorized_message_before' => ['label' => __('Before the unauthorized message', 'wpide')],
            'mepr_unauthorized_message_after' => ['label' => __('After the unauthorized message', 'wpide')],
        ];
    }

    /**
     * Runtime data merged with labels (admin UI and messages).
     */
    public static function all(): array
    {
        $labels = self::labels();
        $all = [];

        foreach (self::runtime() as $id => $location) {
            $all[$id] = $location + ($labels[$id] ?? []) + ['label' => $id, 'description' => ''];
        }

        return $all;
    }

    public static function exists($id): bool
    {
        return is_string($id) && isset(self::runtime()[$id]);
    }

    /**
     * Runtime definition (no labels). Use label() for display.
     */
    public static function get(string $id): ?array
    {
        $runtime = self::runtime();

        return $runtime[$id] ?? null;
    }

    public static function label(string $id): string
    {
        return self::labels()[$id]['label'] ?? $id;
    }

    public static function acceptsType(string $location, string $type): bool
    {
        $definition = self::get($location);

        return $definition && in_array($type, $definition['types'], true);
    }

    public static function isPro(string $location): bool
    {
        $definition = self::get($location);

        return $definition && in_array($definition['group'], self::PRO_GROUPS, true);
    }

    /**
     * A Pro location on a site without Pro: it can't be chosen and nothing is inserted there.
     */
    public static function isLocked(string $location): bool
    {
        return self::isPro($location) && !Plans::pro();
    }

    public static function needsNumber(string $location): bool
    {
        $definition = self::get($location);

        return $definition && !empty($definition['number']);
    }

    public static function needsHookName(string $location): bool
    {
        $definition = self::get($location);

        return $definition && !empty($definition['custom_hook']);
    }

    /**
     * A WordPress hook name: letters, numbers and the separators themes and plugins use.
     */
    public static function isValidHookName(string $name): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_\-.:\/\[\]]{1,191}$/', $name);
    }

    public static function needsSelector(string $location): bool
    {
        $definition = self::get($location);

        return $definition && !empty($definition['selector']);
    }

    /**
     * Whether the plugin a location depends on is active.
     */
    public static function isAvailable(string $location): bool
    {
        $definition = self::get($location);

        if (!$definition) {
            return false;
        }

        switch ($definition['plugin'] ?? '') {
            case 'woocommerce':
                return class_exists('WooCommerce');
            case 'edd':
                return class_exists('Easy_Digital_Downloads');
            case 'memberpress':
                return defined('MEPR_VERSION');
        }

        return true;
    }
}
