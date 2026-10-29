<?php

namespace WPIDE\App\Classes;

use Freemius_Api;
use WPIDE\App\App;
use const WPIDE\Constants\ASSETS_DIR;
use const WPIDE\Constants\ASSETS_URL;
use const WPIDE\Constants\AUTHOR;
use const WPIDE\Constants\DIR;
use const WPIDE\Constants\FS_AI_ADDON_ID;
use const WPIDE\Constants\FS_ID;
use const WPIDE\Constants\FS_KEY;
use const WPIDE\Constants\GTM_ID;
use const WPIDE\Constants\NAME;
use const WPIDE\Constants\SLUG;
use const WPIDE\Constants\VERSION;
class Freemius {
    /**
     * First-year pricing, like WPCode: yearly prices are the renewal prices and the FIRSTYEAR coupon takes $50
     * off the first yearly payment. Plan id => the yearly price the offer was set up for; a plan whose price
     * no longer matches shows no offer. Filter: wpide_intro_offer.
     */
    public const INTRO_OFFER = [
        'coupon'        => 'FIRSTYEAR',
        'discount'      => 50,
        'discount_type' => 'dollar',
        'currency'      => 'usd',
        'plans'         => [
            '17594' => 99,
            '75042' => 149,
            '75044' => 249,
            '75050' => 399,
        ],
    ];

    /** Support: the XplodedThemes support form with WPIDE (product 342263) preselected. Filter: wpide_support_url. */
    public const SUPPORT_URL = 'https://xplodedthemes.com/support/?products=342263';

    private const RESERVED_PRICING_REQUEST_PARAMETERS = [
        'action',
        'bundle_id',
        'contact_url',
        'disable_single_package',
        'fs_wp_endpoint_url',
        'home_url',
        'is_production',
        'license',
        'menu_slug',
        'mode',
        'module_id',
        'plugin_icon',
        'plugin_id',
        'plugin_public_key',
        'plugin_version',
        'pricing_action',
        'request_handler_url',
        's_ctx_id',
        's_ctx_secure',
        's_ctx_ts',
        's_ctx_type',
        'sandbox',
        'security',
        'selector',
        'show_annual_in_monthly',
        'unique_affix'
    ];

    public static $fs;

    public static $api;

    public static $loaded = false;

    /**
     * @throws \Freemius_Exception
     */
    public static function init() {
        if ( !isset( self::$fs ) ) {
            self::sanitizePricingRequest();
            if ( !defined( 'WP_FS__PRODUCT_' . FS_ID . '_MULTISITE' ) ) {
                define( 'WP_FS__PRODUCT_' . FS_ID . '_MULTISITE', true );
            }
            // Init Freemius SDK.
            require_once DIR . 'freemius/start.php';
            self::$fs = fs_dynamic_init( array(
                'id'               => FS_ID,
                'slug'             => SLUG,
                'premium_slug'     => SLUG . '-pro',
                'type'             => 'plugin',
                'public_key'       => FS_KEY,
                'is_premium'       => false,
                'premium_suffix'   => 'Pro',
                'has_addons'       => true,
                'has_paid_plans'   => true,
                'trial'            => array(
                    'days'               => 14,
                    'is_require_payment' => true,
                ),
                'menu'             => array(
                    'slug'        => SLUG,
                    'support'     => false,
                    'affiliation' => false,
                    'addons'      => false,
                    'contact'     => false,
                    'network'     => true,
                ),
                'is_live'          => true,
                'is_org_compliant' => true,
            ) );
            $GLOBALS['wpide_fs'] = self::$fs;
            self::$fs->add_action( 'connect/before', [__CLASS__, 'beforeConnectBox'] );
            self::$fs->add_action( 'connect/after', [__CLASS__, 'afterConnectBox'] );
            self::$fs->add_filter( 'checkout/purchaseCompleted', [__CLASS__, 'afterPurchaseJs'] );
            self::$fs->add_filter( 'templates/checkout.php', [__CLASS__, 'checkoutGtmScript'] );
            self::$fs->add_filter( 'freemius_pricing_js_path', [__CLASS__, 'pricingJsPath'] );
            self::$fs->add_filter( 'pricing/css_path', [__CLASS__, 'pricingCssPath'] );
            self::$fs->add_filter( 'plugin_icon', [__CLASS__, 'pluginIcon'] );
            self::$fs->add_filter( 'hide_account_tabs', '__return_true' );
            self::$fs->add_filter( 'hide_freemius_powered_by', '__return_true' );
            self::$fs->add_filter( 'hide_billing_and_payments_info', '__return_true' );
            self::$fs->add_action( 'plugins_loaded', [__CLASS__, 'override_freemius_strings'] );
            add_action( 'admin_enqueue_scripts', [__CLASS__, 'admin_enqueue_scripts'] );
            add_action( 'admin_footer', [__CLASS__, 'introOfferScript'], 1 );
            // After Freemius adds Account and Pricing, so Support can sit between them as Freemius's own item did.
            add_action( 'admin_menu', [__CLASS__, 'supportMenuItem'], PHP_INT_MAX );
            add_action( 'network_admin_menu', [__CLASS__, 'supportMenuItem'], PHP_INT_MAX );
            add_action( 'admin_footer', [__CLASS__, 'supportMenuScript'] );
            self::$loaded = true;
            // Signal that SDK was initiated.
            do_action( 'wpide_fs_loaded' );
        }
    }

    /**
     * Prevent request parameters from overriding trusted pricing-page config.
     */
    public static function sanitizePricingRequest() : void {
        // Only sanitize when rendering the pricing page — never on admin-ajax,
        // where the pricing JS legitimately passes these same params (incl. `action`).
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            return;
        }
        $page = ( isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '' );
        if ( SLUG . '-pricing' !== $page ) {
            return;
        }
        foreach ( self::RESERVED_PRICING_REQUEST_PARAMETERS as $parameter ) {
            // The WPIDE AI add-on's pricing and checkout are this page with its product id.
            if ( $parameter === 'plugin_id' && self::aiAddonId() !== '' && isset( $_GET['plugin_id'] ) && (string) $_GET['plugin_id'] === self::aiAddonId() ) {
                continue;
            }
            unset($_GET[$parameter], $_REQUEST[$parameter]);
        }
    }

    /**
     * The WPIDE AI add-on's Freemius product id: the constant, the wpide_ai_addon_id filter, or the id the
     * cloud reported last; '' when there is none yet.
     */
    public static function aiAddonId() : string {
        $id = ( FS_AI_ADDON_ID !== '' ? FS_AI_ADDON_ID : (string) get_site_transient( 'wpide_ai_addon_id' ) );
        return preg_replace( '/\\D/', '', (string) apply_filters( 'wpide_ai_addon_id', $id ) );
    }

    /**
     * Where to buy AI credits: the add-on's pricing page inside WPIDE ('' when the add-on does not exist yet).
     */
    public static function aiBuyUrl() : string {
        $id = self::aiAddonId();
        return ( $id !== '' && isset( self::$fs ) ? self::$fs->_get_admin_page_url( 'pricing', [
            'plugin_id' => $id,
        ] ) : '' );
    }

    /**
     * The WPIDE AI add-on's plans for the "Get AI credits" chooser, each opening its checkout inside WPIDE.
     * Freemius's pricing page only shows WPIDE's own plans, so an add-on is bought from its checkout directly.
     * Prices as set in Freemius; filter: wpide_ai_credit_plans.
     */
    public static function aiCreditPlans() : array {
        $id = self::aiAddonId();
        if ( $id === '' || !isset( self::$fs ) ) {
            return [];
        }
        $plans = apply_filters( 'wpide_ai_credit_plans', [
            [
                'plan_id' => '74921',
                'title'   => __( 'AI Starter', 'wpide' ),
                'credits' => __( '400 credits / month', 'wpide' ),
                'price'   => '$5',
                'period'  => __( '/ month', 'wpide' ),
                'yearly'  => __( 'or $49 / year', 'wpide' ),
                'cycle'   => 'monthly',
            ],
            [
                'plan_id'  => '74922',
                'title'    => __( 'AI Pro', 'wpide' ),
                'credits'  => __( '1,500 credits / month', 'wpide' ),
                'price'    => '$15',
                'period'   => __( '/ month', 'wpide' ),
                'yearly'   => __( 'or $149 / year', 'wpide' ),
                'cycle'    => 'monthly',
                'featured' => true,
            ],
            [
                'plan_id' => '74923',
                'title'   => __( 'AI Agency', 'wpide' ),
                'credits' => __( '5,000 credits / month', 'wpide' ),
                'price'   => '$39',
                'period'  => __( '/ month', 'wpide' ),
                'yearly'  => __( 'or $390 / year', 'wpide' ),
                'cycle'   => 'monthly',
            ],
            [
                'plan_id' => '74924',
                'title'   => __( 'Credit pack', 'wpide' ),
                'credits' => __( '1,000 credits that never expire', 'wpide' ),
                'price'   => '$9',
                'period'  => __( 'once', 'wpide' ),
                'yearly'  => '',
                'cycle'   => 'lifetime',
            ]
        ] );
        return array_map( function ( $plan ) use($id) {
            $plan['url'] = self::$fs->_get_admin_page_url( 'pricing', [
                'checkout'      => 'true',
                'plugin_id'     => $id,
                'plan_id'       => $plan['plan_id'],
                'billing_cycle' => $plan['cycle'],
            ] );
            return $plan;
        }, $plans );
    }

    public static function admin_enqueue_scripts() {
        if ( App::instance()->isFreemiusScreen() ) {
            self::freemius_custom_styles();
        }
    }

    public static function freemius_custom_styles() {
        wp_enqueue_style(
            SLUG . '-fs-connect',
            ASSETS_URL . 'css/freemius/freemius.css',
            [],
            VERSION
        );
    }

    public static function beforeConnectBox() {
        wp_enqueue_style(
            SLUG . '-fs-connect',
            ASSETS_URL . 'css/freemius/freemius.css',
            [],
            VERSION
        );
        echo '<div id="fs-connect-wrap">';
    }

    public static function afterConnectBox() {
        echo '</div>';
    }

    public static function afterPurchaseJs() : string {
        return 'function ( data ) {
                    
             /**
             * Since the user just entered their personal & billing information, agreed to the TOS & privacy,
             * know they are running within a secure iframe from an external domain, they implicitly permit tracking
             * this purchase. So initializing GTM here (after the purchase), is legitimate.
             */
             
            var is_subscription = typeof(data.purchase.initial_amount) !== "undefined";
            var is_trial = typeof(data.purchase.trial_ends) !== "undefined" && data.purchase.trial_ends !== null;
            var total = is_subscription ? data.purchase.initial_amount : data.purchase.gross;
            total = is_trial ? 0 : total;
    
            var coupon = data && data.coupon ? data.coupon.code : "";
            var currency = data && data.currency ? data.currency.toUpperCase() : "USD";
    
            var item = {
                item_name: "' . NAME . '",
                item_id: ' . FS_ID . ',
                item_brand: "' . AUTHOR . '",
                affiliation: "' . AUTHOR . '",
                price: data.total,
                discount: 0,
                currency: currency,
                coupon: coupon,
                index: 1,
                quantity: 1
            };
    
            // Purchase Event
            dataLayer.push({ ecommerce: null });  // Clear the previous ecommerce object.
            dataLayer.push({
                event: "purchase",
                ecommerce: {
                    transaction_id: data.purchase.id,
                    affiliation: item.affiliation,
                    tax: 0,
                    shipping: 0,
                    items: [item],
                    currency: item.currency,
                    coupon: item.coupon,
                    value: total
                }
            });
        }';
    }

    public static function checkoutGtmScript( $html ) : string {
        return "\n        <script>window.dataLayer = window.dataLayer || [];</script>\n        <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':\n        new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],\n        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=\n        'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);\n        })(window,document,'script','dataLayer', '" . GTM_ID . "');</script>\n        " . $html;
    }

    public static function pricingJsPath() : string {
        return ASSETS_DIR . 'pricing/freemius-pricing.js';
    }

    /** WPIDE's colors for the shared pricing app (its --fsp-* variables). */
    public static function pricingCssPath() : string {
        return ASSETS_DIR . 'pricing-brand/wpide.css';
    }

    /**
     * The first-year offer for the pricing page (read by its cards and checkout), printed before the page's
     * script in the footer.
     */
    public static function introOfferScript() : void {
        if ( !wp_script_is( 'freemius-pricing', 'enqueued' ) ) {
            return;
        }
        $offer = apply_filters( 'wpide_intro_offer', self::INTRO_OFFER );
        if ( empty( $offer['coupon'] ) ) {
            return;
        }
        wp_add_inline_script( 'freemius-pricing', 'window.FS_PRICING_INTRO_OFFER = ' . wp_json_encode( $offer ) . ';', 'before' );
    }

    public static function supportUrl() : string {
        return (string) apply_filters( 'wpide_support_url', self::SUPPORT_URL );
    }

    /**
     * Support in WPIDE's menu, before Pricing (last when there is none): the XplodedThemes support page.
     */
    public static function supportMenuItem() : void {
        global $submenu;
        if ( !current_user_can( 'manage_options' ) || !isset( $submenu[SLUG] ) ) {
            return;
        }
        $items = array_values( $submenu[SLUG] );
        $at = count( $items );
        foreach ( $items as $index => $item ) {
            if ( ($item[2] ?? '') === SLUG . '-pricing' ) {
                $at = $index;
                break;
            }
        }
        array_splice(
            $items,
            $at,
            0,
            [[__( 'Support', 'wpide' ), 'manage_options', self::supportUrl()]]
        );
        $submenu[SLUG] = $items;
    }

    /**
     * The menu's Support item opens the support page in a new tab, like the header's Support link.
     */
    public static function supportMenuScript() : void {
        printf( '<script>document.querySelectorAll(%s).forEach(function (a) { a.target = "_blank"; a.rel = "noopener"; });</script>', wp_json_encode( '#adminmenu a[href="' . esc_url( self::supportUrl() ) . '"]' ) );
    }

    public static function pluginIcon() : string {
        return ASSETS_DIR . 'img/icon.png';
    }

    /**
     * Add license dialog boxes
     *
     * @since     1.0.0
     */
    public static function addLicenseActivationDialogBox() {
        if ( !did_action( SLUG . '_license_activation_dialog_added' ) && !App::instance()->isFreemiusScreen( 'account' ) ) {
            self::sdk()->_add_license_activation_dialog_box();
            fs_enqueue_local_style( 'fs_dialog_boxes', '/admin/dialog-boxes.css' );
            do_action( SLUG . '_license_activation_dialog_added' );
        }
    }

    public static function showSubmenus() : bool {
        $is_activation_mode = self::sdk()->is_activation_mode();
        if ( $is_activation_mode ) {
            $show_submenus = false;
        } else {
            if ( fs_is_network_admin() ) {
                /**
                 * Add submenu items or action links to network level when plugin was network activated and the super
                 * admin did NOT delegate the connection of all sites to site admins.
                 */
                $show_submenus = App::instance()->isNetworkActive() && (WP_FS__SHOW_NETWORK_EVEN_WHEN_DELEGATED || !self::sdk()->is_network_delegated_connection());
            } else {
                $show_submenus = !App::instance()->isNetworkActive() || self::sdk()->is_delegated_connection();
            }
        }
        return $show_submenus;
    }

    public static function showUpgradeLink() : bool {
        $show_submenus = self::showSubmenus();
        $is_activation_mode = self::sdk()->is_activation_mode();
        $add_upgrade_link = ($show_submenus || $is_activation_mode && self::sdk()->is_only_premium()) && !self::sdk()->is_whitelabeled();
        return $add_upgrade_link && self::sdk()->is_pricing_page_visible() && self::sdk()->is_submenu_item_visible( 'pricing' );
    }

    public static function showTrialLink() : bool {
        return self::sdk()->apply_filters( 'show_trial', true ) && self::sdk()->has_trial_plan() && !self::sdk()->is_trial_utilized();
    }

    public static function sdk() : \Freemius {
        return self::$fs;
    }

    public static function loaded() : bool {
        return self::$loaded;
    }

    public static function override_freemius_strings() : void {
        self::$fs->override_i18n( array(
            'contact-us' => __( 'Support', 'wpide' ),
        ) );
    }

}
