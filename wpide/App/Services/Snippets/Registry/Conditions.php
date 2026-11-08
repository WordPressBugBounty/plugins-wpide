<?php

namespace WPIDE\App\Services\Snippets\Registry;

use WPIDE\App\Services\Snippets\Plans;
use WPIDE\App\Services\Snippets\Runtime\Geo;
use WPIDE\App\Services\Snippets\Runtime\Runtime;

/**
 * Conditional logic: rule definitions and evaluation.
 *
 * Storage format (compatible with WPCode exports):
 *   ['show' => 'show'|'hide', 'groups' => [[ ['type' => ..., 'option' => ..., 'relation' => ..., 'value' => ..., 'meta_key' => ...], ... ], ...]]
 * Rows in a group are ANDed, groups are ORed, 'hide' inverts the result. Unknown types, options or operators
 * are rejected when saving and evaluate to false at runtime (fail closed).
 */
class Conditions
{
    const OPERATORS = ['=', '!=', 'contains', 'notcontains', 'before', 'after', 'before-or', 'after-or'];

    const FIELD_OPERATORS = [
        'select' => ['=', '!='],
        'ajax' => ['=', '!='],
        'text' => ['contains', 'notcontains', '=', '!='],
        'date' => ['=', '!=', 'before', 'after', 'before-or', 'after-or'],
        'datetime' => ['=', '!=', 'before', 'after', 'before-or', 'after-or'],
        'time' => ['=', 'before', 'after', 'before-or', 'after-or'],
    ];

    /**
     * Rules WPCode Lite shows with a PRO badge, plus WPIDE's capability and network rules. Free: logged in, user
     * role, type of page, post type, referrer, taxonomy archive, taxonomy term and page URL. On a site without
     * Pro these rules evaluate to false, as in WPCode Lite.
     */
    const PRO = [
        'user_capability', 'user_meta', 'post_id', 'page_template', 'post_author', 'post_meta',
        'device_type', 'browser', 'os', 'cookie_name', 'cookie_value', 'wc_page', 'wc_cart', 'edd_page',
        'memberpress_page', 'memberpress_user', 'date_is', 'time_is', 'weekday', 'time', 'snippet_loaded',
        'country', 'continent', 'site_id', 'main_site',
    ];

    /** Rules that need a higher plan than Basic, with the plan feature that includes them. */
    const PLAN_FEATURES = ['country' => 'geo_conditions', 'continent' => 'geo_conditions'];

    /** @var array Snippet ids currently being evaluated by snippet_loaded (cycle guard). */
    protected static $evaluating = [];

    public static function operatorLabels(): array
    {
        return [
            '=' => __('Is', 'wpide'),
            '!=' => __('Is not', 'wpide'),
            'contains' => __('Contains', 'wpide'),
            'notcontains' => __('Does not contain', 'wpide'),
            'before' => __('Is before', 'wpide'),
            'after' => __('Is after', 'wpide'),
            'before-or' => __('Is on or before', 'wpide'),
            'after-or' => __('Is on or after', 'wpide'),
        ];
    }

    /**
     * Rule definitions. Choices are only computed when $withChoices is true (admin UI).
     */
    public static function definitions(bool $withChoices = false): array
    {
        $isOneOf = ['=' => __('Is one of', 'wpide'), '!=' => __('Is not one of', 'wpide')];
        $choices = function (callable $provider) use ($withChoices) {
            return $withChoices ? $provider() : [];
        };

        $groups = [
            'user' => [
                'label' => __('User', 'wpide'),
                'options' => [
                    'logged_in' => ['label' => __('Logged in', 'wpide'), 'field' => 'select', 'choices' => [['value' => '1', 'label' => __('Yes', 'wpide')], ['value' => '', 'label' => __('No', 'wpide')]]],
                    'user_role' => ['label' => __('User role', 'wpide'), 'field' => 'select', 'choices' => $choices([__CLASS__, 'roleChoices'])],
                    'user_capability' => ['label' => __('User capability', 'wpide'), 'field' => 'select', 'choices' => $choices([__CLASS__, 'capabilityChoices'])],
                    'user_meta' => ['label' => __('User meta', 'wpide'), 'field' => 'text', 'meta_key' => 'user_meta_key'],
                ],
            ],
            'page' => [
                'label' => __('Page', 'wpide'),
                'options' => [
                    'type_of_page' => ['label' => __('Type of page', 'wpide'), 'field' => 'select', 'choices' => [
                        ['value' => 'is_front_page', 'label' => __('Homepage', 'wpide')],
                        ['value' => 'is_home', 'label' => __('Blog home', 'wpide')],
                        ['value' => 'is_single', 'label' => __('Single post or page', 'wpide')],
                        ['value' => 'is_archive', 'label' => __('Archive', 'wpide')],
                        ['value' => 'is_author', 'label' => __('Author page', 'wpide')],
                        ['value' => 'is_search', 'label' => __('Search results', 'wpide')],
                        ['value' => 'is_404', 'label' => __('404 page', 'wpide')],
                    ]],
                    'post_type' => ['label' => __('Post type', 'wpide'), 'field' => 'select', 'choices' => $choices([__CLASS__, 'postTypeChoices'])],
                    'referrer' => ['label' => __('Referrer', 'wpide'), 'field' => 'text'],
                    'taxonomy_page' => ['label' => __('Taxonomy archive', 'wpide'), 'field' => 'select', 'choices' => $choices([__CLASS__, 'taxonomyChoices'])],
                    'taxonomy_term' => ['label' => __('Taxonomy term', 'wpide'), 'field' => 'ajax', 'search' => 'terms', 'multiple' => true],
                    'page_url' => ['label' => __('Page URL', 'wpide'), 'field' => 'text'],
                    'post_id' => ['label' => __('Post or page', 'wpide'), 'field' => 'ajax', 'search' => 'posts', 'multiple' => true, 'operator_labels' => $isOneOf],
                    'page_template' => ['label' => __('Page template', 'wpide'), 'field' => 'select', 'multiple' => true, 'operator_labels' => $isOneOf, 'choices' => $choices([__CLASS__, 'templateChoices'])],
                    'post_author' => ['label' => __('Author', 'wpide'), 'field' => 'ajax', 'search' => 'users', 'multiple' => true, 'operator_labels' => $isOneOf],
                    'post_meta' => ['label' => __('Post meta', 'wpide'), 'field' => 'text', 'meta_key' => 'meta_key'],
                ],
            ],
            'device' => [
                'label' => __('Device', 'wpide'),
                'options' => [
                    'device_type' => ['label' => __('Device type', 'wpide'), 'field' => 'select', 'choices' => [['value' => 'desktop', 'label' => __('Desktop', 'wpide')], ['value' => 'mobile', 'label' => __('Mobile', 'wpide')]]],
                    'browser' => ['label' => __('Browser', 'wpide'), 'field' => 'select', 'multiple' => true, 'operator_labels' => $isOneOf, 'choices' => [
                        ['value' => 'chrome', 'label' => 'Chrome'], ['value' => 'firefox', 'label' => 'Firefox'], ['value' => 'safari', 'label' => 'Safari'],
                        ['value' => 'opera', 'label' => 'Opera'], ['value' => 'edge', 'label' => 'Edge'], ['value' => 'ie', 'label' => 'Internet Explorer'],
                    ]],
                    'os' => ['label' => __('Operating system', 'wpide'), 'field' => 'select', 'multiple' => true, 'operator_labels' => $isOneOf, 'choices' => [
                        ['value' => 'windows', 'label' => 'Windows'], ['value' => 'mac', 'label' => 'macOS'], ['value' => 'linux', 'label' => 'Linux'],
                        ['value' => 'android', 'label' => 'Android'], ['value' => 'ios', 'label' => 'iOS'],
                    ]],
                    'cookie_name' => ['label' => __('Cookie name', 'wpide'), 'field' => 'text'],
                    'cookie_value' => ['label' => __('Cookie value', 'wpide'), 'field' => 'text'],
                ],
            ],
            'woocommerce' => [
                'label' => __('WooCommerce', 'wpide'),
                'plugin' => 'woocommerce',
                'options' => [
                    'wc_page' => ['label' => __('WooCommerce page', 'wpide'), 'field' => 'select', 'choices' => [
                        ['value' => 'shop', 'label' => __('Shop', 'wpide')], ['value' => 'product', 'label' => __('Single product', 'wpide')],
                        ['value' => 'product_cat', 'label' => __('Product category', 'wpide')], ['value' => 'product_tag', 'label' => __('Product tag', 'wpide')],
                        ['value' => 'cart', 'label' => __('Cart', 'wpide')], ['value' => 'checkout', 'label' => __('Checkout', 'wpide')],
                        ['value' => 'thank_you', 'label' => __('Order received', 'wpide')], ['value' => 'account_page', 'label' => __('My account', 'wpide')],
                    ]],
                    'wc_cart' => ['label' => __('Cart contains', 'wpide'), 'field' => 'ajax', 'search' => 'products', 'multiple' => true, 'operator_labels' => ['=' => __('Contains', 'wpide'), '!=' => __('Does not contain', 'wpide')]],
                ],
            ],
            'edd' => [
                'label' => __('Easy Digital Downloads', 'wpide'),
                'plugin' => 'edd',
                'options' => [
                    'edd_page' => ['label' => __('EDD page', 'wpide'), 'field' => 'select', 'choices' => [
                        ['value' => 'checkout', 'label' => __('Checkout', 'wpide')], ['value' => 'success_page', 'label' => __('Purchase confirmation', 'wpide')],
                        ['value' => 'download_page', 'label' => __('Single download', 'wpide')], ['value' => 'download_category', 'label' => __('Download category', 'wpide')],
                        ['value' => 'download_tag', 'label' => __('Download tag', 'wpide')],
                    ]],
                ],
            ],
            'memberpress' => [
                'label' => __('MemberPress', 'wpide'),
                'plugin' => 'memberpress',
                'options' => [
                    'memberpress_page' => ['label' => __('MemberPress page', 'wpide'), 'field' => 'select', 'choices' => [
                        ['value' => 'registration', 'label' => __('Registration', 'wpide')], ['value' => 'thankyou', 'label' => __('Thank you', 'wpide')],
                        ['value' => 'account', 'label' => __('Account', 'wpide')], ['value' => 'login', 'label' => __('Login', 'wpide')],
                    ]],
                    'memberpress_user' => ['label' => __('Active membership', 'wpide'), 'field' => 'ajax', 'search' => 'memberships', 'multiple' => true, 'operator_labels' => ['=' => __('Is active on', 'wpide'), '!=' => __('Is not active on', 'wpide')]],
                ],
            ],
            'schedule' => [
                'label' => __('Date and time', 'wpide'),
                'options' => [
                    'date_is' => ['label' => __('Date', 'wpide'), 'field' => 'date'],
                    'time_is' => ['label' => __('Date and time', 'wpide'), 'field' => 'datetime'],
                    'weekday' => ['label' => __('Day of the week', 'wpide'), 'field' => 'select', 'multiple' => true, 'choices' => [
                        ['value' => '1', 'label' => __('Monday', 'wpide')], ['value' => '2', 'label' => __('Tuesday', 'wpide')], ['value' => '3', 'label' => __('Wednesday', 'wpide')],
                        ['value' => '4', 'label' => __('Thursday', 'wpide')], ['value' => '5', 'label' => __('Friday', 'wpide')], ['value' => '6', 'label' => __('Saturday', 'wpide')],
                        ['value' => '7', 'label' => __('Sunday', 'wpide')],
                    ]],
                    'time' => ['label' => __('Time of day', 'wpide'), 'field' => 'time'],
                ],
            ],
            'snippet' => [
                'label' => __('Snippet', 'wpide'),
                'options' => [
                    'snippet_loaded' => ['label' => __('Another snippet', 'wpide'), 'field' => 'ajax', 'search' => 'snippets', 'operator_labels' => ['=' => __('Is loaded', 'wpide'), '!=' => __('Is not loaded', 'wpide')]],
                ],
            ],
            'location' => [
                'label' => __('Visitor location', 'wpide'),
                'options' => [
                    'country' => ['label' => __('Country', 'wpide'), 'field' => 'select', 'multiple' => true, 'operator_labels' => $isOneOf, 'choices' => $choices([Geo::class, 'countryChoices'])],
                    'continent' => ['label' => __('Continent', 'wpide'), 'field' => 'select', 'multiple' => true, 'operator_labels' => $isOneOf, 'choices' => $choices([Geo::class, 'continentChoices'])],
                ],
            ],
        ];

        if (is_multisite()) {
            $groups['network'] = [
                'label' => __('Network', 'wpide'),
                'options' => [
                    'site_id' => ['label' => __('Site', 'wpide'), 'field' => 'ajax', 'search' => 'sites', 'multiple' => true, 'operator_labels' => $isOneOf],
                    'main_site' => ['label' => __('Main site', 'wpide'), 'field' => 'select', 'choices' => [['value' => '1', 'label' => __('Yes', 'wpide')], ['value' => '', 'label' => __('No', 'wpide')]]],
                ],
            ];
        }

        return apply_filters('wpide_snippets_conditions', $groups);
    }

    /**
     * Fields merged with evaluators: group => ['options' => option => ['field', 'meta_key', 'callback']].
     */
    public static function runtimeDefinitions(): array
    {
        static $cache = null;

        if ($cache !== null && !has_filter('wpide_snippets_condition_evaluators')) {
            return $cache;
        }

        $evaluators = self::evaluators();
        $definitions = [];

        foreach (self::fields() as $group => $options) {
            foreach ($options as $option => $field) {
                $definitions[$group]['options'][$option] = $field + ['callback' => $evaluators[$group][$option] ?? null];
            }
        }

        foreach ($evaluators as $group => $options) {
            foreach ($options as $option => $callback) {
                if (!isset($definitions[$group]['options'][$option])) {
                    $definitions[$group]['options'][$option] = ['field' => 'text', 'callback' => $callback];
                }
            }
        }

        return $cache = $definitions;
    }

    /**
     * Field type (and meta key field) per condition, without labels (safe before init).
     */
    public static function fields(): array
    {
        return [
            'user' => [
                'logged_in' => ['field' => 'select'],
                'user_role' => ['field' => 'select'],
                'user_capability' => ['field' => 'select'],
                'user_meta' => ['field' => 'text', 'meta_key' => 'user_meta_key'],
            ],
            'page' => [
                'type_of_page' => ['field' => 'select'],
                'post_type' => ['field' => 'select'],
                'referrer' => ['field' => 'text'],
                'taxonomy_page' => ['field' => 'select'],
                'taxonomy_term' => ['field' => 'ajax'],
                'page_url' => ['field' => 'text'],
                'post_id' => ['field' => 'ajax'],
                'page_template' => ['field' => 'select'],
                'post_author' => ['field' => 'ajax'],
                'post_meta' => ['field' => 'text', 'meta_key' => 'meta_key'],
            ],
            'device' => [
                'device_type' => ['field' => 'select'],
                'browser' => ['field' => 'select'],
                'os' => ['field' => 'select'],
                'cookie_name' => ['field' => 'text'],
                'cookie_value' => ['field' => 'text'],
            ],
            'woocommerce' => [
                'wc_page' => ['field' => 'select'],
                'wc_cart' => ['field' => 'ajax'],
            ],
            'edd' => [
                'edd_page' => ['field' => 'select'],
            ],
            'memberpress' => [
                'memberpress_page' => ['field' => 'select'],
                'memberpress_user' => ['field' => 'ajax'],
            ],
            'schedule' => [
                'date_is' => ['field' => 'date'],
                'time_is' => ['field' => 'datetime'],
                'weekday' => ['field' => 'select'],
                'time' => ['field' => 'time'],
            ],
            'snippet' => [
                'snippet_loaded' => ['field' => 'ajax'],
            ],
            'location' => [
                'country' => ['field' => 'select'],
                'continent' => ['field' => 'select'],
            ],
            'network' => [
                'site_id' => ['field' => 'ajax'],
                'main_site' => ['field' => 'select'],
            ],
        ];
    }
    /**
     * Runtime value callbacks per condition, without labels (safe before init).
     */
    public static function evaluators(): array
    {
        return apply_filters('wpide_snippets_condition_evaluators', [
            'user' => [
                'logged_in' => function () {
                                return is_user_logged_in() ? '1' : '';
                            },
                'user_role' => function () {
                                return (array) wp_get_current_user()->roles;
                            },
                'user_capability' => function () {
                                // Every capability the visitor has, so "is one of" matches any selected capability.
                                return array_keys(array_filter((array) wp_get_current_user()->allcaps));
                            },
                'user_meta' => function ($snippet, $row) {
                                $key = isset($row['user_meta_key']) ? (string) $row['user_meta_key'] : '';
                                $userId = get_current_user_id();
        
                                return $key !== '' && $userId ? get_user_meta($userId, $key, true) : '';
                            },
            ],
            'page' => [
                'type_of_page' => [__CLASS__, 'typeOfPage'],
                'post_type' => function () {
                                return (string) get_post_type();
                            },
                'referrer' => function () {
                                return isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
                            },
                'taxonomy_page' => function () {
                                if (!did_action('wp')) {
                                    return '';
                                }
                                $object = get_queried_object();
        
                                return isset($object->taxonomy) ? $object->taxonomy : '';
                            },
                'taxonomy_term' => [__CLASS__, 'currentTerms'],
                'page_url' => [__CLASS__, 'pageUrl'],
                'post_id' => function () {
                                return (string) get_the_ID();
                            },
                'page_template' => function () {
                                return (string) get_page_template_slug();
                            },
                'post_author' => function () {
                                if (!did_action('wp')) {
                                    return '';
                                }
                                $object = get_queried_object();
        
                                return isset($object->post_author) ? (string) $object->post_author : '';
                            },
                'post_meta' => function ($snippet, $row) {
                                $key = isset($row['meta_key']) ? (string) $row['meta_key'] : '';
                                $id = get_the_ID();
        
                                return $key !== '' && $id ? get_post_meta($id, $key, true) : '';
                            },
            ],
            'device' => [
                'device_type' => function () {
                                return wp_is_mobile() ? 'mobile' : 'desktop';
                            },
                'browser' => [__CLASS__, 'browser'],
                'os' => [__CLASS__, 'os'],
                'cookie_name' => function () {
                                return array_map('strval', array_keys($_COOKIE)); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
                            },
                'cookie_value' => function () {
                                return array_map(function ($value) {
                                    return is_scalar($value) ? (string) $value : '';
                                }, array_values($_COOKIE)); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
                            },
            ],
            'woocommerce' => [
                'wc_page' => [__CLASS__, 'wooPage'],
                'wc_cart' => [__CLASS__, 'wooCart'],
            ],
            'edd' => [
                'edd_page' => [__CLASS__, 'eddPage'],
            ],
            'memberpress' => [
                'memberpress_page' => [__CLASS__, 'memberpressPage'],
                'memberpress_user' => [__CLASS__, 'memberpressMemberships'],
            ],
            'schedule' => [
                'date_is' => function () {
                                return wp_date('Y-m-d');
                            },
                'time_is' => function () {
                                return wp_date('Y-m-d H:i');
                            },
                'weekday' => function () {
                                return wp_date('N');
                            },
                'time' => function () {
                                return wp_date('H:i');
                            },
            ],
            'snippet' => [
                'snippet_loaded' => [__CLASS__, 'loadedSnippets'],
            ],
            'location' => [
                'country' => [Geo::class, 'country'],
                'continent' => [Geo::class, 'continent'],
            ],
            'network' => [
                'site_id' => function () {
                                return (string) get_current_blog_id();
                            },
                'main_site' => function () {
                                return is_main_site() ? '1' : '';
                            },
            ],
        ]);
    }

    /**
     * Definitions for the admin UI, without callbacks.
     */
    public static function forUi(): array
    {
        $groups = [];

        foreach (self::definitions(true) as $id => $group) {
            $options = [];

            foreach ($group['options'] as $optionId => $option) {
                unset($option['callback']);
                $option['id'] = $optionId;
                $option['operators'] = self::FIELD_OPERATORS[$option['field']] ?? ['=', '!='];
                $option['multiple'] = !empty($option['multiple']);
                $option['pro'] = self::isPro($optionId);
                // The plan the rule starts at when this site's plan is below it ("Pro"), otherwise empty.
                $option['plan'] = self::planLocked($optionId) ? Plans::feature(self::PLAN_FEATURES[$optionId])['plan'] : '';
                $options[] = $option;
            }

            $groups[] = [
                'id' => $id,
                'label' => $group['label'],
                'available' => self::groupAvailable($group),
                'options' => $options,
            ];
        }

        return $groups;
    }

    public static function isPro(string $option): bool
    {
        return in_array($option, self::PRO, true);
    }

    /** Whether a rule needs a higher plan than this site's (country and continent start at Pro). */
    public static function planLocked(string $option): bool
    {
        return isset(self::PLAN_FEATURES[$option]) && Plans::pro() && !Plans::allows(self::PLAN_FEATURES[$option]);
    }

    public static function label(string $option): string
    {
        foreach (self::definitions() as $group) {
            if (isset($group['options'][$option])) {
                return $group['options'][$option]['label'];
            }
        }

        return $option;
    }

    protected static function groupAvailable(array $group): bool
    {
        switch ($group['plugin'] ?? '') {
            case 'woocommerce':
                return class_exists('WooCommerce');
            case 'edd':
                return class_exists('Easy_Digital_Downloads');
            case 'memberpress':
                return defined('MEPR_VERSION');
        }

        return true;
    }

    /**
     * Validate a rules array. Returns a list of error messages (empty when valid).
     */
    public static function validate($rules): array
    {
        $errors = [];

        if (!is_array($rules) || !in_array($rules['show'] ?? null, ['show', 'hide'], true) || !isset($rules['groups']) || !is_array($rules['groups'])) {
            return [__('Conditions must have a show/hide mode and a list of groups.', 'wpide')];
        }

        $definitions = self::runtimeDefinitions();

        foreach ($rules['groups'] as $g => $group) {
            if (!is_array($group)) {
                /* translators: %d: condition group number */
                $errors[] = sprintf(__('Condition group %d is invalid.', 'wpide'), $g + 1);
                continue;
            }

            foreach ($group as $r => $row) {
                /* translators: 1: group number, 2: rule number */
                $label = sprintf(__('Group %1$d, rule %2$d', 'wpide'), $g + 1, $r + 1);
                $type = $row['type'] ?? '';
                $option = $row['option'] ?? '';
                $relation = $row['relation'] ?? '';

                if (!isset($definitions[$type]['options'][$option])) {
                    /* translators: 1: rule label, 2: condition type and option */
                    $errors[] = sprintf(__('%1$s: unknown condition "%2$s".', 'wpide'), $label, $type . '/' . $option);
                    continue;
                }

                $definition = $definitions[$type]['options'][$option];
                $allowed = self::FIELD_OPERATORS[$definition['field']] ?? ['=', '!='];

                if (!in_array($relation, $allowed, true)) {
                    /* translators: 1: rule label, 2: relation operator */
                    $errors[] = sprintf(__('%1$s: operator "%2$s" is not supported for this condition.', 'wpide'), $label, $relation);
                }

                if (!array_key_exists('value', $row) || (!is_scalar($row['value']) && !is_array($row['value']) && $row['value'] !== null)) {
                    /* translators: %s: rule label */
                    $errors[] = sprintf(__('%s: a value is required.', 'wpide'), $label);
                }

                if (!empty($definition['meta_key']) && trim((string) ($row[$definition['meta_key']] ?? '')) === '') {
                    /* translators: %s: rule label */
                    $errors[] = sprintf(__('%s: a meta key is required.', 'wpide'), $label);
                }

                if (in_array($definition['field'], ['date', 'datetime', 'time'], true) && !is_array($row['value'] ?? null) && strtotime((string) ($row['value'] ?? '')) === false) {
                    /* translators: %s: rule label */
                    $errors[] = sprintf(__('%s: the date or time is not valid.', 'wpide'), $label);
                }
            }
        }

        return $errors;
    }

    /**
     * Whether a snippet's conditions allow it to run on this request, including show/hide.
     */
    public static function passes(array $snippet): bool
    {
        if (empty($snippet['use_conditions'])) {
            return true;
        }

        $rules = $snippet['conditions'] ?? [];
        $met = self::rulesMet($rules, $snippet);

        return ($rules['show'] ?? 'show') === 'hide' ? !$met : $met;
    }

    public static function rulesMet($rules, array $snippet = []): bool
    {
        if (!is_array($rules) || empty($rules['groups'])) {
            return true;
        }

        $definitions = self::runtimeDefinitions();

        foreach ($rules['groups'] as $group) {
            if (self::groupMet((array) $group, $definitions, $snippet)) {
                return true;
            }
        }

        return false;
    }

    protected static function groupMet(array $group, array $definitions, array $snippet): bool
    {
        foreach ($group as $row) {
            if (!self::rowMet((array) $row, $definitions, $snippet)) {
                return false;
            }
        }

        return true;
    }

    protected static function rowMet(array $row, array $definitions, array $snippet): bool
    {
        $type = $row['type'] ?? '';
        $option = $row['option'] ?? '';
        $definition = $definitions[$type]['options'][$option] ?? null;

        if (!$definition || !is_callable($definition['callback'] ?? null)) {
            return false;
        }

        if ((self::isPro($option) && !Plans::pro()) || self::planLocked($option)) {
            return false;
        }

        $relation = $row['relation'] ?? '';

        if (!in_array($relation, self::OPERATORS, true)) {
            return false;
        }

        try {
            $current = call_user_func($definition['callback'], $snippet, $row);
        } catch (\Throwable $e) {
            return false;
        }

        $value = $row['value'] ?? '';

        if (in_array($definition['field'], ['date', 'datetime', 'time'], true) && !is_array($value)) {
            return self::compareTime((string) $current, (string) $value, $relation);
        }

        return self::compare($current, $value, $relation);
    }

    /**
     * Compare the runtime value with the stored value.
     */
    public static function compare($current, $expected, string $relation): bool
    {
        switch ($relation) {
            case '=':
                return self::equals($current, $expected);
            case '!=':
                return !self::equals($current, $expected);
            case 'contains':
                return self::contains($current, $expected);
            case 'notcontains':
                return !self::contains($current, $expected);
        }

        return false;
    }

    protected static function equals($current, $expected): bool
    {
        $normalize = function ($value) {
            if (is_bool($value)) {
                return $value ? '1' : '';
            }

            return is_scalar($value) || $value === null ? (string) $value : $value;
        };

        $current = is_array($current) ? array_map($normalize, $current) : $normalize($current);
        $expected = is_array($expected) ? array_map($normalize, $expected) : $normalize($expected);

        if (is_array($current) && is_array($expected)) {
            return count(array_intersect($current, $expected)) > 0;
        }

        if (is_array($current)) {
            return in_array($expected, $current, true);
        }

        if (is_array($expected)) {
            return in_array($current, $expected, true);
        }

        return $current === $expected;
    }

    protected static function contains($current, $expected): bool
    {
        $needle = is_scalar($expected) ? (string) $expected : '';

        if ($needle === '') {
            return false;
        }

        foreach ((array) $current as $haystack) {
            if (is_scalar($haystack) && strpos((string) $haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    protected static function compareTime(string $current, string $expected, string $relation): bool
    {
        $a = strtotime($current);
        $b = strtotime($expected);

        if ($a === false || $b === false) {
            return false;
        }

        switch ($relation) {
            case '=':
                return $a === $b;
            case '!=':
                return $a !== $b;
            case 'before':
                return $a < $b;
            case 'after':
                return $a > $b;
            case 'before-or':
                return $a <= $b;
            case 'after-or':
                return $a >= $b;
        }

        return false;
    }

    // ---- Runtime value callbacks -------------------------------------------------------------

    public static function typeOfPage(): string
    {
        global $wp_query;

        if (!isset($wp_query) || !did_action('wp')) {
            return '';
        }

        if (is_front_page()) {
            return 'is_front_page';
        }
        if (is_home()) {
            return 'is_home';
        }
        if (is_singular()) {
            return 'is_single';
        }
        if (is_author()) {
            return 'is_author';
        }
        if (is_archive()) {
            return 'is_archive';
        }
        if (is_search()) {
            return 'is_search';
        }
        if (is_404()) {
            return 'is_404';
        }

        return '';
    }

    public static function currentTerms(): array
    {
        if (!did_action('wp')) {
            return [];
        }

        if (is_tax() || is_category() || is_tag()) {
            $object = get_queried_object();

            return isset($object->term_id) ? [(string) $object->term_id] : [];
        }

        if (is_singular()) {
            $ids = wp_get_object_terms(get_the_ID(), get_object_taxonomies(get_post_type()), ['fields' => 'ids']);

            return is_wp_error($ids) ? [] : array_map('strval', $ids);
        }

        return [];
    }

    public static function pageUrl(): string
    {
        global $wp;

        if (is_admin()) {
            $url = isset($_SERVER['REQUEST_URI']) ? admin_url(basename(esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])))) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

            return $url;
        }

        if (isset($wp->request)) {
            $url = trailingslashit(home_url($wp->request));
        } else {
            $path = isset($_SERVER['REQUEST_URI']) ? (string) wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
            $url = home_url($path);
        }

        foreach ($_GET as $key => $value) { // phpcs:ignore WordPress.Security.NonceVerification
            $url = add_query_arg([sanitize_key($key) => is_scalar($value) ? sanitize_text_field(wp_unslash($value)) : ''], $url);
        }

        return $url;
    }

    protected static function userAgent(): string
    {
        return isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
    }

    public static function browser(): string
    {
        $ua = self::userAgent();
        $checks = [
            'Edg' => 'edge', 'Edge' => 'edge', 'OPR/' => 'opera', 'Opera' => 'opera', 'Firefox' => 'firefox', 'FxiOS' => 'firefox',
            'CriOS' => 'chrome', 'Chrome' => 'chrome', 'Safari' => 'safari', 'Trident' => 'ie', 'MSIE' => 'ie',
        ];

        foreach ($checks as $needle => $browser) {
            if (strpos($ua, $needle) !== false) {
                return $browser;
            }
        }

        return '';
    }

    public static function os(): string
    {
        $ua = self::userAgent();
        $checks = ['Windows' => 'windows', 'Android' => 'android', 'iPhone' => 'ios', 'iPad' => 'ios', 'iPod' => 'ios', 'Macintosh' => 'mac', 'Linux' => 'linux'];

        foreach ($checks as $needle => $os) {
            if (strpos($ua, $needle) !== false) {
                return $os;
            }
        }

        return '';
    }

    public static function wooPage(): string
    {
        if (!class_exists('WooCommerce') || !did_action('wp')) {
            return '';
        }

        if (function_exists('is_order_received_page') && is_order_received_page()) {
            return 'thank_you';
        }
        if (function_exists('is_checkout') && is_checkout()) {
            return 'checkout';
        }
        if (function_exists('is_cart') && is_cart()) {
            return 'cart';
        }
        if (function_exists('is_shop') && is_shop()) {
            return 'shop';
        }
        if (function_exists('is_product') && is_product()) {
            return 'product';
        }
        if (function_exists('is_product_category') && is_product_category()) {
            return 'product_cat';
        }
        if (function_exists('is_product_tag') && is_product_tag()) {
            return 'product_tag';
        }
        if (function_exists('is_account_page') && is_account_page()) {
            return 'account_page';
        }

        return '';
    }

    public static function wooCart(): array
    {
        if (!function_exists('WC') || !WC()->cart) {
            return [];
        }

        $ids = [];

        foreach (WC()->cart->get_cart_contents() as $item) {
            if (!empty($item['variation_id'])) {
                $ids[] = (string) $item['variation_id'];
            }
            if (!empty($item['product_id'])) {
                $ids[] = (string) $item['product_id'];
            }
        }

        return array_values(array_unique($ids));
    }

    public static function eddPage(): string
    {
        if (!class_exists('Easy_Digital_Downloads') || !did_action('wp')) {
            return '';
        }

        if (function_exists('edd_is_checkout') && edd_is_checkout()) {
            return 'checkout';
        }
        if (function_exists('edd_is_success_page') && edd_is_success_page()) {
            return 'success_page';
        }
        if (is_singular('download')) {
            return 'download_page';
        }
        if (is_tax('download_category')) {
            return 'download_category';
        }
        if (is_tax('download_tag')) {
            return 'download_tag';
        }

        return '';
    }

    public static function memberpressPage(): string
    {
        global $post;

        if (!class_exists('MeprProduct') || !class_exists('MeprUser') || !did_action('wp')) {
            return '';
        }

        // phpcs:disable WordPress.Security.NonceVerification
        if (isset($_REQUEST['membership'], $_REQUEST['membership_id']) && (isset($_REQUEST['trans_num']) || isset($_REQUEST['transaction_id']))) {
            return 'thankyou';
        }
        // phpcs:enable

        if ($post && \MeprProduct::is_product_page($post)) {
            return 'registration';
        }

        if ($post && \MeprUser::is_account_page($post)) {
            return 'account';
        }

        if (class_exists('MeprOptions')) {
            $loginPage = (int) \MeprOptions::fetch()->login_page_id;

            if ($loginPage > 0 && is_page($loginPage)) {
                return 'login';
            }
        }

        return '';
    }

    public static function memberpressMemberships(): array
    {
        if (!class_exists('MeprUser') || !is_user_logged_in()) {
            return [];
        }

        $user = new \MeprUser(get_current_user_id());

        return array_map('strval', (array) $user->active_product_subscriptions());
    }

    /**
     * Ids of snippets that ran earlier in this request, or that would run here (active, conditions passing).
     */
    public static function loadedSnippets(array $snippet = []): array
    {
        $selfId = (int) ($snippet['id'] ?? 0);
        $loaded = array_map('strval', Runtime::executedIds());

        if ($selfId) {
            self::$evaluating[$selfId] = true;
        }

        foreach (Runtime::manifestSnippets() as $id => $other) {
            if ((int) $id === $selfId || in_array((string) $id, $loaded, true) || isset(self::$evaluating[(int) $id])) {
                continue;
            }

            if ($other['insert_method'] === 'auto' && self::passes($other)) {
                $loaded[] = (string) $id;
            }
        }

        unset(self::$evaluating[$selfId]);

        return $loaded;
    }

    // ---- Choice providers (admin UI) -----------------------------------------------------------

    public static function roleChoices(): array
    {
        $choices = [];

        foreach (wp_roles()->roles as $role => $details) {
            $choices[] = ['value' => $role, 'label' => translate_user_role($details['name'])];
        }

        return $choices;
    }

    public static function capabilityChoices(): array
    {
        $capabilities = [];

        foreach (wp_roles()->roles as $details) {
            foreach (array_keys(array_filter((array) $details['capabilities'])) as $capability) {
                if (strpos($capability, 'level_') !== 0) {
                    $capabilities[$capability] = true;
                }
            }
        }

        ksort($capabilities);

        return array_map(function ($capability) {
            return ['value' => $capability, 'label' => $capability];
        }, array_keys($capabilities));
    }

    public static function postTypeChoices(): array
    {
        $choices = [];

        foreach (get_post_types(['public' => true], 'objects') as $type) {
            $choices[] = ['value' => $type->name, 'label' => $type->label];
        }

        return $choices;
    }

    public static function taxonomyChoices(): array
    {
        $choices = [];

        foreach (get_taxonomies(['public' => true], 'objects') as $taxonomy) {
            if ($taxonomy->name !== 'post_format') {
                $choices[] = ['value' => $taxonomy->name, 'label' => $taxonomy->labels->singular_name];
            }
        }

        return $choices;
    }

    public static function templateChoices(): array
    {
        $choices = [['value' => '', 'label' => __('Default template', 'wpide')]];

        foreach (wp_get_theme()->get_page_templates() as $file => $name) {
            $choices[] = ['value' => $file, 'label' => $name];
        }

        return $choices;
    }
}
