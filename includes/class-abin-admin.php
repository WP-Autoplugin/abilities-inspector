<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
require_once ABIN_PATH . 'includes/class-abin-table.php';

final class WP_ABIN_Admin {
	const SLUG = 'abilities-inspector';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	public static function menu(): void {
		add_management_page(
			__( 'Abilities Inspector', 'abilities-inspector' ),
			__( 'Abilities Inspector', 'abilities-inspector' ),
			'manage_options',
			self::SLUG,
			array( __CLASS__, 'render' )
		);
	}

	public static function enqueue( string $hook ): void {
		if ( $hook !== 'tools_page_' . self::SLUG ) {
			return;
		}

		wp_enqueue_style(
			'abin-admin',
			ABIN_URL . 'assets/admin.css',
			array(),
			ABIN_VERSION
		);

		wp_enqueue_script(
			'abin-admin',
			ABIN_URL . 'assets/admin.js',
			array( 'jquery' ),
			ABIN_VERSION,
			true
		);

		wp_localize_script( 'abin-admin', 'ABIN', array(
			'i18n' => array(
				'details' => __( 'Details', 'abilities-inspector' ),
				'hide_details' => __( 'Hide details', 'abilities-inspector' ),
			),
		) );
	}

	private static function abilities_api_available(): bool {
		return function_exists( 'wp_get_abilities' ) && function_exists( 'wp_get_ability' );
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'abilities-inspector' ) );
		}

		echo '<div class="wrap abin-wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Abilities Inspector', 'abilities-inspector' ) . '</h1>';
		echo '<p class="description abin-subtitle">' . esc_html__( 'Browse all registered abilities on this site and disable or re-enable them.', 'abilities-inspector' ) . '</p>';
		echo '<hr class="wp-header-end" />';

		if ( ! self::abilities_api_available() ) {
			echo '<div class="notice notice-warning"><p>';
			echo esc_html__( 'Abilities API not available. This plugin requires WordPress 6.9+ (or the Abilities API feature plugin).', 'abilities-inspector' );
			echo '</p></div>';
			echo '</div>';
			return;
		}

		$table = new WP_ABIN_Table();

		// Handle actions (toggle/bulk).
		$table->process_actions();

		// Prepare items after processing, so UI reflects changes.
		$table->prepare_items();

		echo '<div class="abin-card">';

		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '" />';
		$table->views();
		$table->search_box( __( 'Search abilities', 'abilities-inspector' ), 'abin-search' );
		$table->display();
		echo '</form>';

		echo '</div>'; // card
		echo '</div>'; // wrap
	}
}
