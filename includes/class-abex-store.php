<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class WP_ABEX_Store {
	const OPTION_DISABLED = 'abex_disabled_abilities';
	const OPTION_EXEC_COUNTS = 'abex_ability_exec_counts';

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

	private static function normalize_counts( $counts ): array {
		if ( ! is_array( $counts ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $counts as $name => $count ) {
			if ( ! is_string( $name ) || $name === '' ) {
				continue;
			}
			if ( is_numeric( $count ) ) {
				$normalized[ $name ] = max( 0, (int) $count );
			}
		}

		return $normalized;
	}

	public static function get_execution_counts(): array {
		return self::normalize_counts( get_option( self::OPTION_EXEC_COUNTS, array() ) );
	}

	public static function get_execution_count( string $name ): int {
		if ( $name === '' ) {
			return 0;
		}
		$counts = self::get_execution_counts();
		return isset( $counts[ $name ] ) ? (int) $counts[ $name ] : 0;
	}

	public static function increment_execution( string $name ): void {
		if ( $name === '' ) {
			return;
		}

		$counts = self::get_execution_counts();
		$counts[ $name ] = isset( $counts[ $name ] ) ? (int) $counts[ $name ] + 1 : 1;
		update_option( self::OPTION_EXEC_COUNTS, $counts, false );
	}
}
