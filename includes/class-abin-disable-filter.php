<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class WP_ABIN_Disable_Filter {
	public static function init(): void {
		// If Abilities API isn't present, do nothing.
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return;
		}
		add_filter( 'wp_register_ability_args', array( __CLASS__, 'filter_register_args' ), 10, 2 );
	}

	/**
	 * Block an ability by overriding args during registration.
	 *
	 * We:
	 * - force show_in_rest=false (so it won't be discoverable / executable via REST)
	 * - replace execute_callback with a callback that denies with WP_Error(403)
	 */
	public static function filter_register_args( array $args, string $name ): array {
		if ( WP_ABIN_Store::is_disabled( $name ) ) {
			$args['show_in_rest'] = false;
			$args['permission_callback'] = '__return_true';
			$args['execute_callback']    = static function( $input = null ) use ( $name ) {
				$message = sprintf( 'Ability "%s" is disabled on this site.', $name );
				$message = apply_filters( 'abin_disabled_ability_message', $message, $name, $input );
				return new WP_Error(
					'abin_ability_disabled',
					$message,
					array( 'status' => 403 )
				);
			};
		}
		return $args;
	}
}
