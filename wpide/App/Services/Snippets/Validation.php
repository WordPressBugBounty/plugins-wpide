<?php

namespace WPIDE\App\Services\Snippets;

use WPIDE\App\Helpers\PhpValidator;
use WPIDE\App\Services\Snippets\Registry\Conditions;
use WPIDE\App\Services\Snippets\Registry\Locations;
use WPIDE\App\Services\Snippets\Registry\Types;

/**
 * Strict snippet validation, applied on save, import, restore and activation.
 *
 * Unsupported types, locations, condition rules or settings are rejected with a visible reason
 * instead of being accepted and ignored at runtime.
 */
class Validation
{
    const MAX_CODE_BYTES = 1048576;

    /**
     * @return array{errors: string[], warnings: string[]}
     */
    public static function check(array $snippet, int $id = 0): array
    {
        $errors = [];
        $warnings = [];
        $type = $snippet['code_type'] ?? '';

        if (!Types::exists($type)) {
            $errors[] = sprintf(__('Unknown code type "%s".', 'wpide'), $type);

            return ['errors' => $errors, 'warnings' => $warnings];
        }

        if (strlen((string) ($snippet['code'] ?? '')) > self::MAX_CODE_BYTES) {
            $errors[] = __('The code is larger than 1 MB.', 'wpide');
        }

        if (($snippet['insert_method'] ?? 'auto') === 'auto') {
            $location = (string) ($snippet['location'] ?? '');

            if ($location === '') {
                $errors[] = __('Choose where the snippet is inserted, or use it as a shortcode.', 'wpide');
            } elseif (!Locations::exists($location)) {
                $errors[] = sprintf(__('Unknown location "%s".', 'wpide'), $location);
            } else {
                if (!Locations::acceptsType($location, $type)) {
                    $errors[] = sprintf(__('%1$s snippets cannot use the "%2$s" location.', 'wpide'), Types::get($type)['label'], Locations::label($location));
                }

                if (Locations::needsNumber($location) && (int) ($snippet['insert_number'] ?? 0) < 1) {
                    $errors[] = __('The insertion number must be 1 or more.', 'wpide');
                }

                if (Locations::needsHookName($location)) {
                    $hook = trim((string) (($snippet['location_extra'] ?? [])['hook_name'] ?? ''));

                    if ($hook === '') {
                        $errors[] = __('Enter the name of the action hook to run on.', 'wpide');
                    } elseif (!Locations::isValidHookName($hook)) {
                        $errors[] = sprintf(__('"%s" is not a valid hook name.', 'wpide'), $hook);
                    }
                }

                if (Locations::needsSelector($location)) {
                    $extra = $snippet['location_extra'] ?? [];
                    $selector = trim((string) ($extra['css_selector'] ?? ''));

                    if ($selector === '') {
                        $errors[] = __('Enter the CSS selector of the element to target.', 'wpide');
                    } elseif (!Runtime\SelectorInserter::isSupportedSelector($selector)) {
                        $errors[] = sprintf(__('The CSS selector "%s" is not supported. Use tag, #id, .class, [attribute] and descendant/child combinations.', 'wpide'), $selector);
                    }

                    if (isset($extra['index']) && $extra['index'] !== '' && (!is_numeric($extra['index']) || (int) $extra['index'] < 1)) {
                        $errors[] = __('The element number must be empty (all matches) or 1 or more.', 'wpide');
                    }
                }

                if (!Locations::isAvailable($location)) {
                    $warnings[] = __('The plugin this location depends on is not active, so the snippet will not be inserted until it is.', 'wpide');
                }
            }
        }

        $shortcode = (string) ($snippet['shortcode'] ?? '');

        if ($shortcode !== '') {
            if (!preg_match('/^[a-z0-9_-]{1,64}$/', $shortcode)) {
                $errors[] = __('Shortcode names can only contain lowercase letters, numbers, dashes and underscores.', 'wpide');
            } elseif ($shortcode === 'wpide_snippet') {
                $errors[] = __('This shortcode name is reserved.', 'wpide');
            } elseif (Repository::findByShortcode($shortcode, $id)) {
                $errors[] = sprintf(__('Another snippet already uses the shortcode [%s].', 'wpide'), $shortcode);
            } elseif (shortcode_exists($shortcode) && !isset(Manifest::get()['shortcodes'][$shortcode])) {
                $errors[] = sprintf(__('The shortcode [%s] is already registered by WordPress or another plugin.', 'wpide'), $shortcode);
            }
        }

        if (!empty($snippet['use_conditions'])) {
            $errors = array_merge($errors, Conditions::validate($snippet['conditions'] ?? []));
        }

        $schedule = $snippet['schedule'] ?? [];
        $start = trim((string) ($schedule['start'] ?? ''));
        $end = trim((string) ($schedule['end'] ?? ''));

        if ($start !== '' && strtotime($start) === false) {
            $errors[] = __('The schedule start date is not valid.', 'wpide');
        }

        if ($end !== '' && strtotime($end) === false) {
            $errors[] = __('The schedule end date is not valid.', 'wpide');
        }

        if ($start !== '' && $end !== '' && strtotime($start) !== false && strtotime($end) !== false && strtotime($start) >= strtotime($end)) {
            $errors[] = __('The schedule must start before it ends.', 'wpide');
        }

        if (!in_array($snippet['device_type'] ?? 'any', ['any', 'desktop', 'mobile'], true)) {
            $errors[] = __('Unknown device type.', 'wpide');
        }

        $syntax = self::syntaxError($type, (string) ($snippet['code'] ?? ''));

        if ($syntax !== null) {
            $errors[] = $syntax;
        }

        if ($type === Types::HTML && preg_match('/<\?(php|=)/i', (string) ($snippet['code'] ?? ''))) {
            $warnings[] = __('HTML snippets never run PHP. Use a Universal snippet to mix HTML and PHP.', 'wpide');
        }

        if (($snippet['device_type'] ?? 'any') !== 'any' || !empty($snippet['use_conditions'])) {
            $warnings = array_merge($warnings, self::cacheWarnings($snippet));
        }

        $locked = Plans::lockedFeatures($snippet);

        if ($locked) {
            $warnings[] = sprintf(__('This snippet uses %s, which need WPIDE Pro. That part does not run until Pro is active.', 'wpide'), implode(', ', $locked));
        }

        return ['errors' => array_values(array_unique($errors)), 'warnings' => array_values(array_unique($warnings))];
    }

    public static function syntaxError(string $type, string $code): ?string
    {
        if (!Types::executesPhp($type) || trim($code) === '') {
            return null;
        }

        $source = $type === Types::PHP ? "<?php\n" . preg_replace('/^\s*<\?php/i', '', $code) : $code;

        try {
            PhpValidator::validate($source);
        } catch (\Throwable $e) {
            $message = $e->getMessage();

            // Report lines relative to the snippet, not the added opening tag.
            if ($type === Types::PHP) {
                $message = preg_replace_callback('/on line (\d+)/', function ($m) {
                    return 'on line ' . max(1, (int) $m[1] - 1);
                }, $message);
            }

            // The parser's own message already starts with "Syntax error" for most failures.
            return stripos($message, 'syntax error') === 0 ? $message : sprintf(__('Syntax error: %s', 'wpide'), $message);
        }

        return null;
    }

    protected static function cacheWarnings(array $snippet): array
    {
        $variable = ($snippet['device_type'] ?? 'any') !== 'any';

        foreach ((array) ($snippet['conditions']['groups'] ?? []) as $group) {
            foreach ((array) $group as $row) {
                if (in_array($row['type'] ?? '', ['device', 'location', 'user'], true) || in_array($row['option'] ?? '', ['referrer'], true)) {
                    $variable = true;
                }
            }
        }

        return $variable ? [__('This snippet depends on the visitor (device, login, cookies, location or referrer). Exclude these pages from full-page caching or the cached variant will be shown to everyone.', 'wpide')] : [];
    }
}
