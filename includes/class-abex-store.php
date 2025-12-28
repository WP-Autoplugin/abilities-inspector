<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class WP_ABEX_Store {
	const OPTION_DISABLED = 'abex_disabled_abilities';

	/**
	 * Returns a set: [ ability_name => true ].
	 */
	public static function get_disabled_set(): array {
		$disabled = get_option( self::OPTION_DISABLED, array() );
		if ( ! is_array( $disabled ) ) {
			$disabled = array();
		}
		$set = array();
		foreach ( $disabled as $name ) {
			if ( is_string( $name ) && $name !== '' ) {
				$set[ $name ] = true;
			}
		}
		return $set;
	}

	public static function set_disabled_set( array $set ): void {
		$names = array_values( array_keys( $set ) );
		sort( $names, SORT_STRING );
		update_option( self::OPTION_DISABLED, $names, false );
	}

	public static function disable( string $name ): void {
		$set = self::get_disabled_set();
		$set[ $name ] = true;
		self::set_disabled_set( $set );
	}

	public static function enable( string $name ): void {
		$set = self::get_disabled_set();
		unset( $set[ $name ] );
		self::set_disabled_set( $set );
	}

	public static function is_disabled( string $name ): bool {
		$set = self::get_disabled_set();
		return isset( $set[ $name ] );
	}
}
