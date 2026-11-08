<?php

/**
 * WPIDE - File Manager & Code Editor
 *
 * @author      XplodedThemes
 * @copyright   2018 XplodedThemes
 * @license     GPL-2.0+
 *
 * @wordpress-plugin
 * Plugin Name: WPIDE - File Manager & Code Editor
 * Plugin URI:  https://wpide.com
 * Description: WordPress file manager and code editor with code snippets, featuring auto-completion of both WordPress and PHP functions with reference, syntax highlighting, line numbers, tabbed editing, automatic backup, image editor & much more!
 * Version:     3.6.4
 * Requires PHP: 7.4.0
 * Requires at least: 5.9
 * Author:      XplodedThemes
 * Author URI:  https://xplodedthemes.com
 * Text Domain: wpide
 * Domain Path: /languages/
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 *
 */
namespace WPIDE;

use WPIDE\App\App;
use WPIDE\App\Classes\Freemius;
defined( 'ABSPATH' ) || exit;
$required_php_version = '7.4.0';
if ( version_compare( PHP_VERSION, $required_php_version, '<' ) ) {
    $class = 'notice notice-error';
    $message = sprintf(
        /* translators: 1: plugin name, 2: required PHP version, 3: current PHP version, 4: plugin name, 5: last compatible plugin version */
        __( '%1$s minimum PHP version requirement is %2$s. The current PHP version is: %3$s. Either update the PHP version, or use %4$s %5$s or below.', 'wpide' ),
        '<strong>WPIDE</strong>',
        '<strong>v' . $required_php_version . '</strong>',
        '<strong>v' . PHP_VERSION . '</strong>',
        '<strong>WPIDE</strong>',
        '<strong>v2.6</strong>'
    );
    add_action( 'admin_notices', function () use($message, $class) {
        echo sprintf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), wp_kses( $message, [
            'strong' => [],
        ] ) );
        deactivate_plugins( __FILE__ );
    } );
} else {
    define( 'WPIDE_FILE', __FILE__ );
    define( 'WPIDE_DIR', __DIR__ );
    global $wpide_fs;
    if ( !empty( $wpide_fs ) ) {
        $wpide_fs->set_basename( true, __FILE__ );
    } else {
        require_once __DIR__ . '/vendor/autoload.php';
        Freemius::init();
        App::instance();
        // Snippets runtime: runs on every request, independently of the admin application. Free, like WPCode Lite.
        \WPIDE\App\Services\Snippets\Snippets::boot();
    }
}