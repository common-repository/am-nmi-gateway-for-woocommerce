<?php
/**
 * The plugin bootstrap file for AM NMI Gateway for WooCommerce
 *
 * This file initializes the plugin, loads dependencies, and registers activation/deactivation hooks.
 *
 * @link              https://profiles.wordpress.org/rushikshah
 * @since             1.0.0
 * @package           Am_Nmi_Gateway_For_Woocommerce
 *
 * @wordpress-plugin
 * Plugin Name:       AM NMI Gateway for WooCommerce
 * Plugin URI:        https://wordpress.org/plugins/am-nmi-gateway-woocommerce
 * Description:       A payment gateway plugin for WooCommerce to integrate NMI (Network Merchants, Inc.) with your WooCommerce store.
 * Version:           1.0.0
 * Author:            RushikShah
 * Author URI:        https://profiles.wordpress.org/rushikshah
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       am-nmi-gateway-for-woocommerce
 * Domain Path:       /languages
 */

// Prevent direct access to the file
if (!defined('WPINC')) {
    die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'AM_NMI_GATEWAY_FOR_WOOCOMMERCE_VERSION', '1.0.0' );

/**
 * Initialize NMI Payment Gateway for WooCommerce after plugins are loaded.
 */
add_action('plugins_loaded', 'am_nmi_wc_gateway_init', 0);
function am_nmi_wc_gateway_init()
{
    // Ensure WooCommerce payment gateway classes are available
    if (!class_exists('WC_Payment_Gateway_CC')) {
        return;
    }

    // Load plugin text domain for translations
    load_plugin_textdomain('am-nmi-gateway-for-woocommerce', false, dirname(plugin_basename(__FILE__)) . '/languages');

    // Include the credit card gateway file
    require_once plugin_dir_path(__FILE__) . 'includes/class-am-nmi-creditcard.php';
    require_once plugin_dir_path(__FILE__) . 'includes/class-am-nmi-logger.php';

    /**
     * Register the NMI credit card and eCheck gateways with WooCommerce.
     *
     * @param array $gateways List of available payment gateways.
     * @return array Updated list of payment gateways.
     */
    function am_nmi_add_woocommerce_gateways($gateways)
    {
        $gateways[] = 'AM_NMI_WooCommerce_CreditCard_Gateway';

        return $gateways;
    }
    add_filter('woocommerce_payment_gateways', 'am_nmi_add_woocommerce_gateways');
}
