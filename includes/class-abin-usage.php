<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class WP_ABIN_Usage {
	public static function init(): void {
		// If Abilities API isn't present, do nothing.
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return;
		}
		add_action( 'wp_after_execute_ability', array( __CLASS__, 'track' ), 10, 3 );
	}

	public static function track( string $ability_name, $input, $result ): void {
		WP_ABIN_Store::increment_execution( $ability_name );
	}
}
