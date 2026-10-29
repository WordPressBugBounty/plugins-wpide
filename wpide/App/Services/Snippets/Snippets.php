<?php

namespace WPIDE\App\Services\Snippets;

use WPIDE\App\Classes\Freemius;
use WPIDE\App\Services\Snippets\Runtime\Runtime;
/**
 * Snippets module bootstrap.
 *
 * Loaded on every request from wpide.php, independently of the admin application. Only light hooks are added
 * here; the schema is installed from admin, activation or WP-CLI, never during a visitor request.
 *
 * The module is free, like WPCode Lite. Pro modules (page scripts, special files, the snippet block and
 * Elementor widget, WPIDE Cloud, conversion tracking) are only part of the premium version and only start
 * while Pro can be used; Plans::pro() gates the Pro parts of the shared code.
 */
class Snippets {
    /** @var bool|null */
    protected static $available;

    public static function boot() {
        Capabilities::init();
        Runtime::boot();
        add_action( 'admin_init', [__CLASS__, 'maybeInstall'], 1 );
        add_action( 'init', [GlobalScripts::class, 'boot'], 5 );
        AdminBar::boot();
        LivePreview::boot();
        Network::boot();
        PublicLibrary::boot();
        add_action( 'wp_abilities_api_categories_init', [Integrations\Abilities::class, 'registerCategory'] );
        add_action( 'wp_abilities_api_init', [Integrations\Abilities::class, 'register'] );
        add_action( 'wpide_snippet_deleted', [__CLASS__, 'cleanupFiles'] );
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            add_action( 'plugins_loaded', [Cli::class, 'register'], 20 );
        }
    }

    /**
     * The snippets module itself is free (WPCode Lite has free snippets); the filter can turn it off.
     */
    public static function available() : bool {
        if ( self::$available !== null ) {
            return self::$available;
        }
        return self::$available = (bool) apply_filters( 'wpide_snippets_available', true );
    }

    public static function resetAvailability() {
        self::$available = null;
        Plans::reset();
    }

    public static function maybeInstall() {
        if ( self::available() && current_user_can( Capabilities::VIEW ) ) {
            Schema::maybeInstall();
        }
    }

    public static function cleanupFiles( int $id ) {
        \WPIDE\App\Services\Snippets\Runtime\PhpFiles::purge( $id );
        \WPIDE\App\Services\Snippets\Runtime\Assets::purge( $id );
    }

}
