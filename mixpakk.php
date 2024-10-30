<?php

/**
 * Plugin Name: Mixpakk Futárszolgálat és Webshoplogisztika
 * Requires Plugins: woocommerce
 * Description: Mixpakk API csomagfeladás
 * Author: Pintér Gergely
 * Author URI: https://mxp.hu
 * Author Email: it@mxp.hu
 * Developer: Mixpakk Kft.
 * Developer URI: https://mxp.hu
 * Text Domain: mxp
 * Version: 1.4.0
 *
 * License: GNU General Public License v3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * WC requires at least: 3.0.0
 * WC tested up to: 8.9.3
 */

include_once __DIR__ . '/inc/helpers.php';
include_once __DIR__ . '/inc/mixpakk-exception.class.php';
include_once __DIR__ . '/inc/mixpakk.class.php';
include_once __DIR__ . '/inc/mixpakk-api.class.php';
include_once __DIR__ . '/inc/mixpakk-filter.class.php';
include_once __DIR__ . '/inc/mixpakk-settings.class.php';
include_once __DIR__ . '/inc/mixpakk-custom.php';

// is_plugin_active function miatt
include_once(ABSPATH . 'wp-admin/includes/plugin.php');

require 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$UpdateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/Mixpakk-Kft/mxp_woocommerce_plugin/',
	__FILE__,
	'mxp_woocommerce_plugin'
);

$UpdateChecker->getVcsApi()->enableReleaseAssets();

if (!defined('ABSPATH')) 
{
    exit; // Exit if accessed directly
}

function mixpakk_plugin_declare_hpos_compat() 
{
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) 
    {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 
            'custom_order_tables', 
            __FILE__, 
            true
        );
    }
}

if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) 
{
    define('MIXPAKK_DIR_URL', plugin_dir_url(__FILE__));

    add_action('before_woocommerce_init', 'mixpakk_plugin_declare_hpos_compat');

    $mixpakk_settings_obj = new Mixpakk_Settings();
    $mixpakk = new Mixpakk($mixpakk_settings_obj);
    $mixpakk_filter = new Mixpakk_Filter($mixpakk, $mixpakk_settings_obj);
}
