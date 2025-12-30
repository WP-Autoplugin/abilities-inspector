<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class WP_ABIN_Origin {
	private static $origins = array();

	public static function init(): void {
		add_filter( 'wp_register_ability_args', array( __CLASS__, 'capture_origin' ), 5, 2 );
	}

	public static function capture_origin( array $args, string $name ): array {
		if ( ! isset( self::$origins[ $name ] ) ) {
			$origin = self::detect_origin();
			if ( ! empty( $origin ) ) {
				self::$origins[ $name ] = $origin;
			}
		}
		return $args;
	}

	public static function get( string $name ): array {
		return isset( self::$origins[ $name ] ) ? self::$origins[ $name ] : array();
	}

	private static function detect_origin(): array {
		$trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );
		$content_dir = wp_normalize_path( WP_CONTENT_DIR );
		$plugin_dir = wp_normalize_path( WP_PLUGIN_DIR );
		$mu_dir = defined( 'WPMU_PLUGIN_DIR' ) ? wp_normalize_path( WPMU_PLUGIN_DIR ) : '';
		$child_theme_dir = wp_normalize_path( get_stylesheet_directory() );
		$parent_theme_dir = wp_normalize_path( get_template_directory() );
		$abin_path = wp_normalize_path( ABIN_PATH );
		$root = wp_normalize_path( ABSPATH );

		foreach ( $trace as $frame ) {
			if ( empty( $frame['file'] ) ) {
				continue;
			}
			$file = wp_normalize_path( $frame['file'] );
			if ( strpos( $file, $abin_path ) === 0 ) {
				continue;
			}
			if ( strpos( $file, $content_dir ) === 0 ) {
				return self::describe_content_file( $file, $plugin_dir, $mu_dir, $child_theme_dir, $parent_theme_dir );
			}
		}

		foreach ( $trace as $frame ) {
			if ( empty( $frame['file'] ) ) {
				continue;
			}
			$file = wp_normalize_path( $frame['file'] );
			if ( strpos( $file, $abin_path ) === 0 ) {
				continue;
			}
			if ( strpos( $file, $root ) === 0 ) {
				return array(
					'type' => 'core',
					'label' => 'WordPress Core',
					'file' => $file,
					'file_display' => self::pretty_path( $file ),
				);
			}
		}

		return array();
	}

	private static function describe_content_file( string $file, string $plugin_dir, string $mu_dir, string $child_theme_dir, string $parent_theme_dir ): array {
		if ( $mu_dir && strpos( $file, $mu_dir ) === 0 ) {
			return array(
				'type' => 'mu-plugin',
				'label' => self::plugin_label_from_file( $file, true ),
				'file' => $file,
				'file_display' => self::pretty_path( $file ),
			);
		}

		if ( $plugin_dir && strpos( $file, $plugin_dir ) === 0 ) {
			return array(
				'type' => 'plugin',
				'label' => self::plugin_label_from_file( $file, false ),
				'file' => $file,
				'file_display' => self::pretty_path( $file ),
			);
		}

		if ( $child_theme_dir && strpos( $file, $child_theme_dir ) === 0 ) {
			$theme = wp_get_theme();
			$label = $theme && $theme->exists() ? $theme->get( 'Name' ) : '';
			return array(
				'type' => 'theme',
				'label' => $label,
				'file' => $file,
				'file_display' => self::pretty_path( $file ),
			);
		}

		if ( $parent_theme_dir && strpos( $file, $parent_theme_dir ) === 0 ) {
			$theme = wp_get_theme( get_template() );
			$label = $theme && $theme->exists() ? $theme->get( 'Name' ) : '';
			return array(
				'type' => 'theme',
				'label' => $label,
				'file' => $file,
				'file_display' => self::pretty_path( $file ),
			);
		}

		return array(
			'type' => 'content',
			'label' => 'wp-content',
			'file' => $file,
			'file_display' => self::pretty_path( $file ),
		);
	}

	private static function plugin_label_from_file( string $file, bool $is_mu ): string {
		if ( ! function_exists( 'plugin_basename' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$basename = plugin_basename( $file );
		$label = $basename;

		if ( function_exists( 'get_plugins' ) ) {
			$plugins = get_plugins();
			if ( isset( $plugins[ $basename ]['Name'] ) && $plugins[ $basename ]['Name'] !== '' ) {
				$label = $plugins[ $basename ]['Name'];
			}
		}

		if ( $is_mu && function_exists( 'get_mu_plugins' ) ) {
			$mu_plugins = get_mu_plugins();
			$mu_key = basename( $file );
			if ( isset( $mu_plugins[ $mu_key ]['Name'] ) && $mu_plugins[ $mu_key ]['Name'] !== '' ) {
				$label = $mu_plugins[ $mu_key ]['Name'];
			} elseif ( isset( $mu_plugins[ $basename ]['Name'] ) && $mu_plugins[ $basename ]['Name'] !== '' ) {
				$label = $mu_plugins[ $basename ]['Name'];
			}
		}

		return $label;
	}

	private static function pretty_path( string $file ): string {
		$file = wp_normalize_path( $file );
		$content_dir = wp_normalize_path( WP_CONTENT_DIR );
		$content_real = realpath( WP_CONTENT_DIR );
		if ( $content_real ) {
			$content_real = wp_normalize_path( $content_real );
		}
		$root = wp_normalize_path( ABSPATH );
		$root_real = realpath( ABSPATH );
		if ( $root_real ) {
			$root_real = wp_normalize_path( $root_real );
		}

		if ( strpos( $file, $content_dir . '/' ) === 0 ) {
			return 'wp-content/' . ltrim( substr( $file, strlen( $content_dir ) ), '/' );
		}

		if ( $content_real && strpos( $file, $content_real . '/' ) === 0 ) {
			return 'wp-content/' . ltrim( substr( $file, strlen( $content_real ) ), '/' );
		}

		if ( strpos( $file, $root . '/' ) === 0 ) {
			return ltrim( substr( $file, strlen( $root ) ), '/' );
		}

		if ( $root_real && strpos( $file, $root_real . '/' ) === 0 ) {
			return ltrim( substr( $file, strlen( $root_real ) ), '/' );
		}

		return $file;
	}
}
