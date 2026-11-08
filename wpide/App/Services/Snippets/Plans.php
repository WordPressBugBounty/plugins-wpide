<?php

namespace WPIDE\App\Services\Snippets;

use WPIDE\App\Classes\Freemius;
use WPIDE\App\Services\Snippets\Registry\Conditions;
use WPIDE\App\Services\Snippets\Registry\Locations;
use WPIDE\App\Services\Snippets\Registry\Types;
/**
 * Free and Pro snippets, and plan-based features, following WPCode.
 *
 * Free WPIDE matches WPCode Lite: the core snippet types, the global, single and archive locations, the basic
 * condition rules, the library, generators, import and export, header and footer scripts and error handling.
 * Everything WPCode Lite shows as a Pro upsell needs WPIDE Pro (see Types, Locations and Conditions for the
 * per-item split, and PRO_FIELDS below for the editor options).
 *
 * Paid features follow WPCode's plans, adapted to WPIDE: Basic (1 site) has every module from before 3.6.0
 * and all snippet Pro features, Plus (5 sites) adds conversion tracking and the Media Optimizer, Pro (25
 * sites) adds access control, location rules by country, cloud deployments and Database Search & Replace, and
 * the top plan (100 sites) adds multisite. The plan comes from the Freemius license; licenses of the original
 * single Pro plan ("premium", sold for 1, 3 and 30 sites) map by their site count, so nobody loses a feature.
 */
class Plans {
    /** Plan tiers, lowest first. 0 is the free plan. */
    const BASIC = 1;

    const PLUS = 2;

    const PRO = 3;

    const TOP = 4;

    /** Freemius plan names per tier (the top plan may be named either way). */
    const PLAN_NAMES = [
        'basic'  => self::BASIC,
        'plus'   => self::PLUS,
        'pro'    => self::PRO,
        'bundle' => self::TOP,
        'agency' => self::TOP,
    ];

    /** The first plan that includes each feature. */
    const FEATURES = [
        'tracking'        => self::PLUS,
        'auto_tracking'   => self::PLUS,
        'media_optimizer' => self::PLUS,
        'access_control'  => self::PRO,
        'geo_conditions'  => self::PRO,
        'deployments'     => self::PRO,
        'search_replace'  => self::PRO,
        'multisite'       => self::TOP,
    ];

    /**
     * Editor options WPCode Lite shows with a PRO badge and never saves: custom shortcode name, schedule,
     * device type. On the free plan they keep their stored value and are ignored at runtime.
     */
    const PRO_FIELDS = ['shortcode', 'schedule', 'device_type'];

    /** Per-snippet settings that are Pro: load as a file, compress output, network (multisite). */
    const PRO_SETTINGS = ['load_as_file', 'compress_output', 'network'];

    /** @var bool|null */
    protected static $pro;

    /** @var int|null */
    protected static $tier;

    /**
     * Whether Pro snippet features can be used: the premium version with premium code usable (trial, active
     * license, or an expired license whose plan keeps features enabled). Always false in the free version.
     */
    public static function pro() : bool {
        if ( self::$pro !== null ) {
            return self::$pro;
        }
        $pro = false;
        // The license is only final once plugins are loaded.
        return ( did_action( 'plugins_loaded' ) ? self::$pro = (bool) $pro : (bool) $pro );
    }

    public static function reset() {
        self::$pro = null;
        self::$tier = null;
    }

    /**
     * The license's plan tier: 0 on the free plan, then BASIC to TOP. The original single Pro plan maps by its
     * site count: 30 sites or unlimited is the top plan, 3 sites Plus, 1 site Basic.
     */
    public static function tier() : int {
        if ( !self::pro() ) {
            return 0;
        }
        if ( self::$tier !== null ) {
            return self::$tier;
        }
        $plan = ( Freemius::loaded() ? Freemius::sdk()->get_plan() : null );
        $name = ( is_object( $plan ) ? strtolower( (string) $plan->name ) : '' );
        $tier = self::PLAN_NAMES[$name] ?? 0;
        if ( !$tier || $name === 'basic' ) {
            $sites = self::sites();
            $tier = ( $sites >= 30 ? self::TOP : (( $sites >= 3 ? self::PLUS : self::BASIC )) );
        }
        $tier = max( 0, min( self::TOP, (int) apply_filters( 'wpide_plan_tier', $tier, $name ) ) );
        return ( did_action( 'plugins_loaded' ) ? self::$tier = $tier : $tier );
    }

    /** A plan's name as customers see it. */
    public static function label( int $tier ) : string {
        $labels = [
            self::BASIC => __( 'Basic', 'wpide' ),
            self::PLUS  => __( 'Plus', 'wpide' ),
            self::PRO   => __( 'Pro', 'wpide' ),
            self::TOP   => (string) apply_filters( 'wpide_top_plan_label', __( 'Bundle', 'wpide' ) ),
        ];
        return $labels[$tier] ?? __( 'Free', 'wpide' );
    }

    public static function sites() : int {
        $license = ( Freemius::loaded() ? Freemius::sdk()->_get_license() : null );
        $quota = ( is_object( $license ) ? $license->quota : null );
        $sites = ( $quota === null ? PHP_INT_MAX : (int) $quota );
        return (int) apply_filters( 'wpide_snippets_plan_sites', $sites );
    }

    /** The first plan tier that includes a feature (BASIC for anything not listed). */
    public static function requirement( string $feature ) : int {
        $features = (array) apply_filters( 'wpide_plan_features', self::FEATURES );
        return (int) ($features[$feature] ?? self::BASIC);
    }

    public static function allows( string $feature ) : bool {
        return self::pro() && self::tier() >= self::requirement( $feature );
    }

    /** What the UI needs about one feature: whether it works here and the plan that includes it. */
    public static function feature( string $feature ) : array {
        $tier = self::requirement( $feature );
        return [
            'allowed' => self::allows( $feature ),
            'tier'    => $tier,
            'plan'    => self::label( $tier ),
        ];
    }

    /**
     * On the free plan, Pro editor options keep their stored value (defaults for a new snippet), as WPCode Lite
     * never saves them.
     */
    public static function keepProFields( array $snippet, ?array $current ) : array {
        if ( self::pro() ) {
            return $snippet;
        }
        $base = ( $current ?: Repository::defaults() );
        foreach ( self::PRO_FIELDS as $field ) {
            $snippet[$field] = $base[$field];
        }
        foreach ( self::PRO_SETTINGS as $setting ) {
            $snippet['settings'][$setting] = $base['settings'][$setting] ?? Repository::defaults()['settings'][$setting];
        }
        return $snippet;
    }

    /**
     * Pro features a snippet uses that are locked on this site: code type, location and condition rules.
     *
     * @return string[] labels
     */
    public static function lockedFeatures( array $snippet ) : array {
        if ( self::pro() ) {
            return [];
        }
        $locked = [];
        $type = (string) ($snippet['code_type'] ?? '');
        if ( Types::isPro( $type ) ) {
            /* translators: %s: snippet type label */
            $locked[] = sprintf( __( '%s snippets', 'wpide' ), Types::get( $type )['label'] ?? $type );
        }
        if ( ($snippet['insert_method'] ?? 'auto') === 'auto' && Locations::isPro( (string) ($snippet['location'] ?? '') ) ) {
            /* translators: %s: location label */
            $locked[] = sprintf( __( 'the "%s" location', 'wpide' ), Locations::label( (string) $snippet['location'] ) );
        }
        if ( !empty( $snippet['use_conditions'] ) ) {
            foreach ( (array) ($snippet['conditions']['groups'] ?? []) as $group ) {
                foreach ( (array) $group as $row ) {
                    $option = (string) ($row['option'] ?? '');
                    if ( Conditions::isPro( $option ) ) {
                        /* translators: %s: condition label */
                        $locked[] = sprintf( __( 'the "%s" condition', 'wpide' ), Conditions::label( $option ) );
                    }
                }
            }
        }
        return array_values( array_unique( $locked ) );
    }

    /**
     * Locked Pro features the editor added: those the stored snippet did not already use. A site that moved
     * from Pro to free can still edit its snippets without removing what it set up before.
     *
     * @return string[] labels
     */
    public static function newlyLocked( array $snippet, ?array $current ) : array {
        return array_values( array_diff( self::lockedFeatures( $snippet ), ( $current ? self::lockedFeatures( $current ) : [] ) ) );
    }

    public static function forUi() : array {
        $features = [];
        foreach ( array_keys( self::FEATURES ) as $feature ) {
            $features[$feature] = self::feature( $feature );
        }
        return [
            'pro'      => self::pro(),
            'tier'     => self::tier(),
            'plan'     => self::label( self::tier() ),
            'sites'    => ( self::sites() === PHP_INT_MAX ? null : self::sites() ),
            'features' => $features,
        ];
    }

}
