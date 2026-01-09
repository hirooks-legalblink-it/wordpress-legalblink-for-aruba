<?php
/**
 * Plugin Name: LegalBlink for Aruba
 * Plugin URI: https://wordpress.org/plugins/legalblink-for-aruba/
 * Description: Integrate LegalBlink services from Aruba in your WordPress site. Generate GDPR-compliant legal documents including Privacy Policy, Cookie Policy, and Terms & Conditions with professional legal support.
 * Version: 1.0.0
 * Author: LegalBlink
 * Author URI: https://legalblink.it/
 * Text Domain: legalblink-for-aruba
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Network: true
 *
 * SOURCE CODE REPOSITORY:
 * This plugin contains compiled JavaScript and CSS assets (in /assets/admin-ui/).
 * The human-readable source code is available at:
 * https://github.com/YOUR-USERNAME/legalblink-plugin-wp-refactor
 *
 * The admin interface source code is in the 'admin-ui/' directory.
 * Build instructions are available in README-DEVELOPERS.md
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

if (!defined('ABSPATH')) {
    die;
}

// Define plugin directory path
if (!defined('LBFA_PLUGIN_DIR')) {
    define('LBFA_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

require_once __DIR__ . '/vendor/autoload.php';

function lbfa_init()
{
    try {
        // Initialize logger
        LBFA_Logger::info('Plugin initialization started', LBFA_Logger::CATEGORY_GENERAL, 'lbfa_init');

        // Inizializza i componenti principali
        LBFA_Main_API_Controller::get_instance();
        LBFA_Frontend_Manager::get_instance();
        LBFA_Shortcode_Manager::get_instance();

        LBFA_Logger::info('Plugin initialization completed successfully', LBFA_Logger::CATEGORY_GENERAL, 'lbfa_init');
    } catch (Exception $e) {
        LBFA_Logger::critical('Plugin initialization failed: ' . $e->getMessage(), LBFA_Logger::CATEGORY_GENERAL, 'lbfa_init');
    }
}

// Inizializza il plugin dopo che WordPress ha caricato tutti i plugin
add_action('plugins_loaded', 'lbfa_init');

function lbfa_add_type_attribute( array $attr )
{
    $scripts_type_module = ['lbfa_admin-ui-main-script-js'];

    if (in_array($attr['id'], $scripts_type_module, true)) {
        $attr['type'] = 'module';
    }

    return $attr;
}

add_filter('wp_script_attributes', 'lbfa_add_type_attribute', 10, 3);

function lbfa_enqueue_admin_assets()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $full_url = rest_url(LBFA_Base_API_Controller::get_api_namespace());
    $relative_url = wp_parse_url( $full_url, PHP_URL_PATH );

    $api_config = [
        'baseUrl' => '',
        'root' => esc_url_raw( $relative_url ),
        'nonce' => wp_create_nonce('wp_rest'),
        'editPagesUrl' => admin_url('edit.php?post_type=page'),
    ];
    $admin_ui_url = plugin_dir_url(__FILE__) . 'assets/admin-ui/';
    $version = '1.0.0';

    wp_enqueue_style(
        'lbfa_admin-ui-main-style',
        $admin_ui_url . 'style.css',
        [],
        $version
    );

    wp_enqueue_script(
        'lbfa_admin-ui-main-script',
        $admin_ui_url . 'index.js',
        [],
        $version,
        [
            'in_footer' => false,
        ]
    );

    wp_add_inline_script(
        'lbfa_admin-ui-main-script',
        'var lbfa = ' . wp_json_encode($api_config) . ';',
        'before'
    );

    // Render the admin page
    lbfa_render_admin_page();
}

function lbfa_render_admin_page()
{
    ?>
    <div id="lbfa_app"></div>
    <?php
}

// Menu admin
add_action('admin_menu', function () {
    $menu_title = 'LegalBlink per Aruba';
    add_menu_page(
        $menu_title,
        $menu_title,
        'manage_options',
        'lbfa_admin',
        'lbfa_enqueue_admin_assets',
        'dashicons-shield-alt',
        85
    );
});

// Network admin menu (multisite)
add_action('network_admin_menu', function () {
    $menu_title = 'LegalBlink per Aruba';
    add_menu_page(
        $menu_title,
        $menu_title,
        'manage_network_options',
        'lbfa_network_admin',
        'lbfa_enqueue_admin_assets',
        'dashicons-shield-alt',
        85
    );
});
