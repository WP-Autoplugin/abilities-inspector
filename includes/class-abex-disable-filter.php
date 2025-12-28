<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class WP_ABEX_Disable_Filter {
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
	 * - replace permission_callback with a callback that denies with WP_Error(403)
	 */
	public static function filter_register_args( array $args, string $name ): array {
		if ( WP_ABEX_Store::is_disabled( $name ) ) {
			$args['show_in_rest'] = false;

			$args['permission_callback'] = static function() use ( $name ) {
				return new WP_Error(
					'abex_ability_disabled',
					sprintf( 'Ability "%s" is disabled by Abilities Explorer.', $name ),
					array( 'status' => 403 )
				);
			};
		}
		return $args;
	}
}
