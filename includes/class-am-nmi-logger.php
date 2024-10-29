<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NMI logging class which saves important data to the log
 *
 * @since 1.0.0
 */
class AM_NMI_WooCommerce_Logger {

	public static $logger;

	/**
	 * What rolls down stairs
	 * alone or in pairs,
	 * and over your neighbor's dog?
	 * What's great for a snack,
	 * And fits on your back?
	 * It's log, log, log
	 *
	 * @since 1.0.0
	 */
	public static function log( $message ) {

		if ( ! class_exists( 'WC_Logger' ) ) {
			return;
		}

		if ( empty( self::$logger ) ) {
			self::$logger = wc_get_logger();
		}

		self::$logger->debug( $message, array( 'source' => 'woocommerce-gateway-nmi' ) );

	}

}

new AM_NMI_WooCommerce_Logger();
