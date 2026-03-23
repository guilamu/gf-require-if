<?php
/**
 * Plugin Name: Gravity Forms Require If
 * Plugin URI:  https://github.com/guilamu/gravity-forms-require-if
 * Description: Make the required state of supported Gravity Forms fields conditional.
 * Version:     1.0.0
 * Author:      Guilamu
 * Author URI:  https://github.com/guilamu
 * Text Domain: gf-require-if
 * Domain Path: /languages
 * Update URI:  https://github.com/guilamu/gravity-forms-require-if/
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * License:     AGPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/agpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'GRFI_VERSION', '1.0.0' );
define( 'GRFI_PATH', plugin_dir_path( __FILE__ ) );
define( 'GRFI_URL', plugin_dir_url( __FILE__ ) );
define( 'GRFI_FILE', __FILE__ );

// GitHub auto-updater.
require_once GRFI_PATH . 'includes/class-github-updater.php';

// Bootstrap via GFAddOn framework.
add_action( 'gform_loaded', array( 'GF_Require_If_Bootstrap', 'load' ), 5 );

class GF_Require_If_Bootstrap {

    public static function load() {
        if ( ! method_exists( 'GFForms', 'include_addon_framework' ) ) {
            return;
        }
        require_once GRFI_PATH . 'class-gf-require-if.php';
        GFAddOn::register( 'GF_Require_If' );
    }
}

function gf_require_if() {
    return GF_Require_If::get_instance();
}

// Register with Guilamu Bug Reporter.
add_action( 'plugins_loaded', function () {
    if ( class_exists( 'Guilamu_Bug_Reporter' ) ) {
        Guilamu_Bug_Reporter::register( array(
            'slug'        => 'gravity-forms-require-if',
            'name'        => 'Gravity Forms Require If',
            'version'     => GRFI_VERSION,
            'github_repo' => 'guilamu/gravity-forms-require-if',
        ) );
    }
}, 20 );

// Bug Reporter link in plugins list.
add_filter( 'plugin_row_meta', 'grfi_plugin_row_meta', 10, 2 );

function grfi_plugin_row_meta( array $links, string $file ): array {
    if ( plugin_basename( GRFI_FILE ) !== $file ) {
        return $links;
    }

    if ( class_exists( 'Guilamu_Bug_Reporter' ) ) {
        $links[] = sprintf(
            '<a href="#" class="guilamu-bug-report-btn" data-plugin-slug="gravity-forms-require-if" data-plugin-name="%s">%s</a>',
            esc_attr__( 'Gravity Forms Require If', 'gf-require-if' ),
            esc_html__( '🐛 Report a Bug', 'gf-require-if' )
        );
    } else {
        $links[] = sprintf(
            '<a href="%s" target="_blank">%s</a>',
            'https://github.com/guilamu/guilamu-bug-reporter/releases',
            esc_html__( '🐛 Report a Bug (install Bug Reporter)', 'gf-require-if' )
        );
    }

    return $links;
}
