<?php
/**
 * Nota Logger
 * 
 * @package NotaPlugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Nota_Logger main class
 */
class Nota_Logger {

	/**
	 * Logs messages
	 * 
	 * @param string $message The message to log.
	 */
	public static function log( $message ) {
		error_log( '[NOTA]: ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	/**
	 * Logs messages when in debug mode
	 * 
	 * @param string $message The message to log.
	 */
	public static function debug( $message ) {
		if ( ! Nota::is_debug_mode() ) {
			return;
		}
		self::log( $message );
	}

	/**
	 * Log errors
	 * 
	 * @param string $message The message to log.
	 */
	public static function error( $message ) {
		self::log( $message );
	}
}
