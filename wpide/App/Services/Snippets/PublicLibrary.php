<?php

namespace WPIDE\App\Services\Snippets;

use WPIDE\App\Classes\Freemius;
use const WPIDE\Constants\SLUG;
use const WPIDE\Constants\VERSION;

/**
 * The public snippet library on WPIDE Cloud, for free and Pro: WPIDE team snippets and snippets other users
 * published. The Library tab shows it from a cached copy (refreshed twice a day, with the bundled library as
 * the fallback), so the library works offline.
 *
 * Publishing and signing in to the library website use the site's Freemius install (the identity the WPIDE
 * license uses): requests are signed with its key, which only this site and Freemius know. No account or
 * password is involved.
 */
class PublicLibrary
{
    const DEFAULT_URL = 'https://cloud.wpide.com';
    const DEFAULT_WEBSITE = 'https://wpide.com';
    const CACHE_OPTION = 'wpide_library_cloud';
    const PUBLISHED_OPTION = 'wpide_library_published';
    const REFRESH_HOOK = 'wpide_library_refresh';
    const TTL = 12 * HOUR_IN_SECONDS;
    const RETRY = HOUR_IN_SECONDS;

    public static function boot()
    {
        add_action(self::REFRESH_HOOK, [__CLASS__, 'refresh']);
        add_action('admin_init', [__CLASS__, 'handleLinks']);

        if (!wp_next_scheduled(self::REFRESH_HOOK)) {
            wp_schedule_event(time() + 300, 'twicedaily', self::REFRESH_HOOK);
        }
    }

    /** WPIDE Cloud's address (WPIDE_CLOUD_URL in wp-config.php or the wpide_cloud_url filter change it). */
    public static function cloudUrl(): string
    {
        return untrailingslashit((string) apply_filters('wpide_cloud_url', defined('WPIDE_CLOUD_URL') ? WPIDE_CLOUD_URL : self::DEFAULT_URL));
    }

    /** The library website, the only place a sign-in code may be sent to. */
    public static function website(): string
    {
        return untrailingslashit((string) apply_filters('wpide_library_website', defined('WPIDE_LIBRARY_WEBSITE') ? WPIDE_LIBRARY_WEBSITE : self::DEFAULT_WEBSITE));
    }

    // The cached library

    /**
     * The cloud library as last fetched (categories and snippets), or null before the first fetch. With
     * $refresh, fetches it in this request when the copy is older than 12 hours (one attempt an hour when the
     * cloud is unreachable).
     */
    public static function cached(bool $refresh = true): ?array
    {
        $cache = get_option(self::CACHE_OPTION);

        if ($refresh && (!is_array($cache) || (int) ($cache['checked'] ?? 0) < time() - self::TTL)) {
            $cache = self::refresh() ?? $cache;
        }

        return is_array($cache) && !empty($cache['data']['snippets']) ? $cache['data'] : null;
    }

    public static function refresh(): ?array
    {
        $cache = get_option(self::CACHE_OPTION);
        $cache = is_array($cache) ? $cache : [];
        $headers = ['Accept' => 'application/json'];

        if (!empty($cache['etag']) && !empty($cache['data'])) {
            $headers['If-None-Match'] = $cache['etag'];
        }

        $response = wp_remote_get(self::cloudUrl() . '/v1/public/library/index', ['timeout' => 8, 'headers' => $headers]);
        $status = is_wp_error($response) ? 0 : (int) wp_remote_retrieve_response_code($response);

        if ($status === 304) {
            $cache['checked'] = time();
        } elseif ($status === 200) {
            $data = json_decode((string) wp_remote_retrieve_body($response), true);

            if (!is_array($data) || !isset($data['snippets']) || !is_array($data['snippets'])) {
                return null;
            }

            $cache = ['etag' => (string) wp_remote_retrieve_header($response, 'etag'), 'checked' => time(), 'data' => ['categories' => $data['categories'] ?? [], 'snippets' => $data['snippets']]];
        } else {
            // Unreachable: keep the old copy and try again in an hour.
            $cache['checked'] = time() - self::TTL + self::RETRY;
            update_option(self::CACHE_OPTION, $cache, false);

            return null;
        }

        update_option(self::CACHE_OPTION, $cache, false);

        return $cache;
    }

    // Signed requests (Freemius install)

    /**
     * This site's Freemius install, when the site is connected to Freemius (opted in, or a license activated).
     */
    public static function identity(): ?array
    {
        $identity = null;

        if (Freemius::loaded() && Freemius::sdk()->is_registered()) {
            $site = Freemius::sdk()->get_site();
            $identity = $site && !empty($site->id) && !empty($site->secret_key) ? ['id' => (string) $site->id, 'secret' => (string) $site->secret_key] : null;
        }

        // Tests and staging sites can sign as another Freemius install (one the cloud they talk to knows).
        $identity = apply_filters('wpide_library_identity', $identity);

        return is_array($identity) && !empty($identity['id']) && !empty($identity['secret']) ? $identity : null;
    }

    public static function signingString(string $method, string $path, string $timestamp, string $nonce, string $body): string
    {
        return implode("\n", [strtoupper($method), $path, $timestamp, $nonce, hash('sha256', $body)]);
    }

    /**
     * A request to WPIDE Cloud signed with this site's Freemius install key.
     *
     * @throws LibraryException
     */
    public static function request(string $method, string $path, ?array $body = null, int $timeout = 20): array
    {
        $identity = self::identity();

        if (!$identity) {
            throw new LibraryException(esc_html__('Connect this site to WPIDE first: open WPIDE > Account and allow the connection (it uses your email address, no password). Free sites can connect too.', 'wpide'), 0, 'not_registered');
        }

        $raw = $body === null ? '' : (string) wp_json_encode($body);
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(16));
        $headers = [
            'X-WPIDE-FS-Install' => $identity['id'],
            'X-WPIDE-Timestamp' => $timestamp,
            'X-WPIDE-Nonce' => $nonce,
            'X-WPIDE-Signature' => hash_hmac('sha256', self::signingString($method, $path, $timestamp, $nonce, $raw), $identity['secret']),
            'X-WPIDE-Plugin' => VERSION,
        ];

        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
        }

        return self::decode(wp_remote_request(self::cloudUrl() . $path, ['method' => strtoupper($method), 'timeout' => $timeout, 'headers' => $headers, 'body' => $body === null ? null : $raw]));
    }

    /**
     * @param array|\WP_Error $response
     *
     * @throws LibraryException
     */
    public static function decode($response): array
    {
        if (is_wp_error($response)) {
            /* translators: %s: connection error message */
            throw new LibraryException(esc_html(sprintf(__('WPIDE Cloud could not be reached: %s', 'wpide'), $response->get_error_message())), 0, 'unreachable');
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $data = json_decode((string) wp_remote_retrieve_body($response), true);

        if ($status < 200 || $status >= 300) {
            /* translators: %d: HTTP status code */
            $message = is_array($data) && !empty($data['message']) ? (string) $data['message'] : sprintf(__('WPIDE Cloud returned an error (%d).', 'wpide'), $status);

            throw new LibraryException(esc_html($message), (int) $status, esc_html(is_array($data) ? (string) ($data['error'] ?? '') : ''));
        }

        return is_array($data) ? $data : [];
    }

    // Reading one snippet and counting installs

    /**
     * One library snippet by its address, including link-only ones (and this site's own private ones).
     */
    public static function fetch(string $slug): ?array
    {
        if (!preg_match('/^[a-z0-9-]{2,120}$/', $slug)) {
            return null;
        }

        try {
            return self::identity()
                ? self::request('GET', '/v1/public/library/' . $slug)
                : self::decode(wp_remote_get(self::cloudUrl() . '/v1/public/library/' . $slug, ['timeout' => 10]));
        } catch (LibraryException $e) {
            return null;
        }
    }

    /** Counted on the library website; never delays the install. */
    public static function installed(string $slug)
    {
        wp_remote_post(self::cloudUrl() . '/v1/public/library/' . rawurlencode($slug) . '/installed', ['timeout' => 2, 'blocking' => false]);
    }

    // Publishing

    /**
     * Publishes one of this site's snippets to the library, or updates it when it was published before.
     *
     * @throws LibraryException
     */
    public static function publish(int $snippetId, array $input): array
    {
        $snippet = Repository::find($snippetId);

        if (!$snippet) {
            throw new LibraryException(esc_html__('Snippet not found.', 'wpide'));
        }

        $entry = [
            'title' => sanitize_text_field($input['title'] ?? $snippet['title']),
            'description' => sanitize_textarea_field($input['description'] ?? ''),
            'category' => sanitize_key($input['category'] ?? ''),
            'tags' => array_values(array_filter(array_map('sanitize_key', (array) ($input['tags'] ?? array_diff((array) ($snippet['tags'] ?? []), ['library']))))),
            'code' => (string) $snippet['code'],
            'code_type' => $snippet['code_type'],
            'insert_method' => ($snippet['insert_method'] ?? 'auto') === 'shortcode' ? 'shortcode' : 'auto',
            'location' => (string) ($snippet['location'] ?: 'everywhere'),
            'insert_number' => max(1, (int) ($snippet['insert_number'] ?? 1)),
            'requires' => array_values(array_intersect((array) ($input['requires'] ?? []), ['woocommerce', 'edd', 'memberpress'])),
            'notes' => sanitize_textarea_field($input['notes'] ?? ''),
            'visibility' => in_array($input['visibility'] ?? '', ['public', 'unlisted', 'private'], true) ? $input['visibility'] : 'public',
        ];
        $published = (array) get_option(self::PUBLISHED_OPTION, []);
        $slug = $published[$snippetId] ?? '';

        try {
            $result = $slug !== '' ? self::request('PUT', '/v1/public/snippets/' . $slug, $entry) : self::request('POST', '/v1/public/snippets', $entry);
        } catch (LibraryException $e) {
            // Deleted on the website: publish it again as a new library snippet.
            if ($slug === '' || $e->getCode() !== 404) {
                throw $e;
            }

            $result = self::request('POST', '/v1/public/snippets', $entry);
        }

        $published[$snippetId] = $result['id'];
        update_option(self::PUBLISHED_OPTION, $published, false);

        return $result;
    }

    /**
     * @throws LibraryException
     */
    public static function unpublish(int $snippetId): void
    {
        $published = (array) get_option(self::PUBLISHED_OPTION, []);

        if (!empty($published[$snippetId])) {
            try {
                self::request('DELETE', '/v1/public/snippets/' . $published[$snippetId]);
            } catch (LibraryException $e) {
                if ($e->getCode() !== 404) {
                    throw $e;
                }
            }
        }

        unset($published[$snippetId]);
        update_option(self::PUBLISHED_OPTION, $published, false);
    }

    /** The library address a snippet of this site was published at, if any. */
    public static function publishedSlug(int $snippetId): string
    {
        return (string) (((array) get_option(self::PUBLISHED_OPTION, []))[$snippetId] ?? '');
    }

    /**
     * This site's author and the snippets it published (with where each stands: waiting for review, published...).
     *
     * @throws LibraryException
     */
    public static function mine(): array
    {
        try {
            $data = self::request('GET', '/v1/public/me');
        } catch (LibraryException $e) {
            // A cloud without the public library (an older or wrong address) answers 404 for the route itself.
            if ((int) $e->getCode() === 404) {
                /* translators: %s: WPIDE Cloud URL */
                throw new LibraryException(esc_html(sprintf(__('The WPIDE library is not available at %s yet. Try again later.', 'wpide'), self::cloudUrl())), 404, 'library_unavailable');
            }

            throw $e;
        }

        $bySlug = array_flip((array) get_option(self::PUBLISHED_OPTION, []));

        $data['snippets'] = array_map(function ($snippet) use ($bySlug) {
            return $snippet + ['snippet_id' => (int) ($bySlug[$snippet['id']] ?? 0)];
        }, (array) ($data['snippets'] ?? []));

        return $data;
    }

    // Links from the library website

    /**
     * wp-admin/admin.php?page=wpide&wpide_library_open=<slug> opens a library snippet in the Library tab;
     * &wpide_library_login=1&state=..&return=.. signs in to the library website after the user confirms.
     */
    public static function handleLinks()
    {
        if (isset($_GET['wpide_library_open'])) {
            $slug = sanitize_key(wp_unslash($_GET['wpide_library_open']));
            wp_safe_redirect(admin_url('admin.php?page=' . SLUG . '#/snippets/library?open=' . rawurlencode($slug)));
            exit;
        }

        if (!isset($_GET['wpide_library_login'])) {
            return;
        }

        if (!current_user_can(Capabilities::VIEW) && !current_user_can('manage_options')) {
            wp_die(esc_html__('You need access to WPIDE snippets to sign in to the library.', 'wpide'), '', ['response' => 403]);
        }

        $state = isset($_GET['state']) ? preg_replace('/[^A-Za-z0-9_-]/', '', sanitize_text_field(wp_unslash($_GET['state']))) : '';
        $return = isset($_GET['return']) ? esc_url_raw(wp_unslash($_GET['return'])) : '';
        $website = self::website();

        // The code only ever goes back to the library website.
        if (strlen($state) < 16 || strpos($return, $website . '/library/') !== 0) {
            wp_die(esc_html__('This sign-in link is not valid. Start again from the WPIDE library website.', 'wpide'), '', ['response' => 400]);
        }

        if (isset($_POST['wpide_library_confirm'])) {
            check_admin_referer('wpide_library_login');

            try {
                $code = self::request('POST', '/v1/public/login-codes', ['state' => $state]);
                wp_redirect(add_query_arg(['code' => $code['code'], 'state' => $state], $return)); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- checked against the library website above.
                exit;
            } catch (LibraryException $e) {
                wp_die(esc_html($e->getMessage()), esc_html__('WPIDE library', 'wpide'), ['response' => 200, 'back_link' => true]);
            }
        }

        $host = wp_parse_url($website, PHP_URL_HOST);
        $form = '<h1>' . esc_html__('Sign in to the WPIDE library', 'wpide') . '</h1>'
            /* translators: 1: site name, 2: site URL */
            . '<p>' . sprintf(esc_html__('%1$s asks to sign you in with this site (%2$s). Snippets you publish there will show the name on this site\'s WPIDE account.', 'wpide'), '<strong>' . esc_html($host) . '</strong>', esc_html(home_url())) . '</p>';

        if (!self::identity()) {
            $form .= '<p>' . esc_html__('This site is not connected to WPIDE yet. Open WPIDE > Account, allow the connection, then try again.', 'wpide') . '</p>';
        } else {
            $form .= '<form method="post">' . wp_nonce_field('wpide_library_login', '_wpnonce', true, false)
                . '<p><button class="button button-primary" name="wpide_library_confirm" value="1">' . esc_html__('Sign in', 'wpide') . '</button> '
                . '<a class="button" href="' . esc_url($website . '/library/') . '">' . esc_html__('Cancel', 'wpide') . '</a></p></form>';
        }

        wp_die($form, esc_html__('WPIDE library', 'wpide'), ['response' => 200]); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
    }
}
