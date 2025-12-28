<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class WP_ABIN_Table extends WP_List_Table {
	private $items_raw = array();
	private $total_count = 0;
	private $disabled_count = 0;

	public function __construct() {
		parent::__construct( array(
			'singular' => 'ability',
			'plural'   => 'abilities',
			'ajax'     => false,
		) );
	}

	public function get_total_count(): int {
		return (int) $this->total_count;
	}

	public function get_disabled_count(): int {
		return (int) $this->disabled_count;
	}

	public function process_actions(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action = $this->current_action();
		if ( ! $action ) {
			return;
		}

		// Nonce.
		check_admin_referer( 'abin_action', 'abin_nonce' );

		// Single toggle.
		if ( in_array( $action, array( 'enable', 'disable' ), true ) ) {
			$name = isset( $_GET['ability'] ) ? sanitize_text_field( wp_unslash( $_GET['ability'] ) ) : '';
			if ( $name !== '' ) {
				if ( $action === 'disable' ) {
					WP_ABIN_Store::disable( $name );
				} else {
					WP_ABIN_Store::enable( $name );
				}
				add_action( 'admin_notices', function() use ( $action ) {
					echo '<div class="notice notice-success is-dismissible"><p>';
					echo esc_html( $action === 'disable' ? __( 'Ability disabled.', 'abilities-inspector' ) : __( 'Ability enabled.', 'abilities-inspector' ) );
					echo '</p></div>';
				} );
			}
			return;
		}

		// Bulk.
		if ( $action === 'bulk-disable' || $action === 'bulk-enable' ) {
			$names = isset( $_GET['ability_name'] ) ? (array) $_GET['ability_name'] : array();
			$count = 0;

			foreach ( $names as $raw ) {
				$name = sanitize_text_field( wp_unslash( $raw ) );
				if ( $name === '' ) {
					continue;
				}
				if ( $action === 'bulk-disable' ) {
					WP_ABIN_Store::disable( $name );
				} else {
					WP_ABIN_Store::enable( $name );
				}
				$count++;
			}

			add_action( 'admin_notices', function() use ( $action, $count ) {
				echo '<div class="notice notice-success is-dismissible"><p>';
				if ( $count === 0 ) {
					echo esc_html__( 'No abilities selected.', 'abilities-inspector' );
				} else {
					echo esc_html(
						$action === 'bulk-disable'
							? sprintf( _n( '%d ability disabled.', '%d abilities disabled.', $count, 'abilities-inspector' ), $count )
							: sprintf( _n( '%d ability enabled.', '%d abilities enabled.', $count, 'abilities-inspector' ), $count )
					);
				}
				echo '</p></div>';
			} );
			return;
		}
	}

	public function prepare_items(): void {
		$disabled_set = WP_ABIN_Store::get_disabled_set();

		$abilities = function_exists( 'wp_get_abilities' ) ? wp_get_abilities() : array();
		if ( ! is_array( $abilities ) ) {
			$abilities = array();
		}

		// Normalize into arrays for display.
		$items = array();
		foreach ( $abilities as $name => $ability ) {
			$items[] = $this->extract_ability_data( $name, $ability, $disabled_set );
		}

		// Count totals.
		$this->total_count = count( $items );
		$this->disabled_count = 0;
		foreach ( $items as $it ) {
			if ( ! empty( $it['disabled'] ) ) {
				$this->disabled_count++;
			}
		}

		// Apply filters (status/category/search).
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'all';
		$category = isset( $_GET['category'] ) ? sanitize_text_field( wp_unslash( $_GET['category'] ) ) : '';
		$search = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';

		if ( $status === 'enabled' ) {
			$items = array_values( array_filter( $items, fn($i) => empty( $i['disabled'] ) ) );
		} elseif ( $status === 'disabled' ) {
			$items = array_values( array_filter( $items, fn($i) => ! empty( $i['disabled'] ) ) );
		}

		if ( $category !== '' ) {
			$items = array_values( array_filter( $items, fn($i) => (string) $i['category'] === (string) $category ) );
		}

		if ( $search !== '' ) {
			$needle = mb_strtolower( $search );
			$items = array_values( array_filter( $items, function( $i ) use ( $needle ) {
				$hay = mb_strtolower( $i['name'] . ' ' . $i['label'] . ' ' . $i['description'] . ' ' . $i['category_label'] );
				return strpos( $hay, $needle ) !== false;
			} ) );
		}

		// Sorting.
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'name';
		$order   = isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'asc';

		usort( $items, function( $a, $b ) use ( $orderby, $order ) {
			$va = $a[ $orderby ] ?? '';
			$vb = $b[ $orderby ] ?? '';
			switch ( $orderby ) {
				case 'executions':
				case 'disabled':
					$cmp = (int) $va <=> (int) $vb;
					break;
				default:
					$cmp = strcasecmp( (string) $va, (string) $vb );
			}
			return ( $order === 'desc' ) ? -$cmp : $cmp;
		} );

		// Pagination.
		$per_page = 20;
		$current_page = $this->get_pagenum();
		$total_items = count( $items );

		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $per_page,
		) );

		$offset = ( $current_page - 1 ) * $per_page;
		$this->items_raw = $items;
		$this->items = array_slice( $items, $offset, $per_page );

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'name' );
	}

	private function extract_ability_data( string $name, $ability, array $disabled_set ): array {
		$label = $this->read_field( $ability, array( 'get_label', 'label' ), '' );
		$description = $this->read_field( $ability, array( 'get_description', 'description' ), '' );
		$category = $this->read_field( $ability, array( 'get_category', 'category' ), '' );
		$show_in_rest = $this->read_bool_field( $ability, array( 'show_in_rest', 'get_show_in_rest', 'show_in_rest' ), null );

		// Optional: annotations / schema / etc.
		$annotations = $this->read_field( $ability, array( 'get_annotations', 'annotations' ), null );
		$input_schema = $this->read_field( $ability, array( 'get_input_schema', 'input_schema' ), null );
		$output_schema = $this->read_field( $ability, array( 'get_output_schema', 'output_schema' ), null );

		$disabled = isset( $disabled_set[ $name ] );

		$category_label = $category;
		if ( function_exists( 'wp_get_ability_categories' ) ) {
			$cats = wp_get_ability_categories();
			if ( is_array( $cats ) && isset( $cats[ $category ] ) ) {
				$cat_obj = $cats[ $category ];
				$category_label = $this->read_field( $cat_obj, array( 'get_label', 'label' ), $category );
			}
		}

		return array(
			'name' => $name,
			'label' => (string) $label,
			'description' => (string) $description,
			'category' => (string) $category,
			'category_label' => (string) $category_label,
			'show_in_rest' => is_bool( $show_in_rest ) ? $show_in_rest : null,
			'disabled' => (bool) $disabled,
			'executions' => WP_ABIN_Store::get_execution_count( $name ),
			'annotations' => $annotations,
			'input_schema' => $input_schema,
			'output_schema' => $output_schema,
		);
	}

	private function read_field( $obj, array $candidates, $default ) {
		foreach ( $candidates as $key ) {
			if ( is_string( $key ) && method_exists( $obj, $key ) ) {
				try {
					return $obj->$key();
				} catch ( \Throwable $e ) {
					// ignore
				}
			}
			if ( is_string( $key ) && is_object( $obj ) && isset( $obj->$key ) ) {
				return $obj->$key;
			}
		}
		return $default;
	}

	private function read_bool_field( $obj, array $candidates, $default ) {
		$val = $this->read_field( $obj, $candidates, $default );
		if ( is_bool( $val ) ) return $val;
		if ( is_string( $val ) ) {
			if ( $val === '1' || strtolower( $val ) === 'true' ) return true;
			if ( $val === '0' || strtolower( $val ) === 'false' ) return false;
		}
		return $default;
	}

	public function get_columns(): array {
		return array(
			'cb'          => '<input type="checkbox" />',
			'name'        => __( 'Ability', 'abilities-inspector' ),
			'category'    => __( 'Category', 'abilities-inspector' ),
			'executions'  => __( 'Executions', 'abilities-inspector' ),
			'status'      => __( 'Status', 'abilities-inspector' ),
			'details'     => '',
		);
	}

	protected function get_sortable_columns(): array {
		return array(
			'name'     => array( 'name', true ),
			'category' => array( 'category_label', false ),
			'executions' => array( 'executions', false ),
			'status'   => array( 'disabled', false ),
		);
	}

	protected function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="ability_name[]" value="%s" />',
			esc_attr( $item['name'] )
		);
	}

	public function get_bulk_actions(): array {
		return array(
			'bulk-disable' => __( 'Disable', 'abilities-inspector' ),
			'bulk-enable'  => __( 'Enable', 'abilities-inspector' ),
		);
	}

	public function no_items() {
		esc_html_e( 'No abilities found.', 'abilities-inspector' );
	}

	protected function get_views() {
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'all';
		$base_url = remove_query_arg( array( 'status', 'paged' ) );
		$views = array();

		$views['all'] = sprintf(
			'<a href="%s" class="%s">%s</a>',
			esc_url( add_query_arg( 'status', 'all', $base_url ) ),
			$status === 'all' ? 'current' : '',
			esc_html__( 'All', 'abilities-inspector' )
		);
		$views['enabled'] = sprintf(
			'<a href="%s" class="%s">%s</a>',
			esc_url( add_query_arg( 'status', 'enabled', $base_url ) ),
			$status === 'enabled' ? 'current' : '',
			esc_html__( 'Enabled', 'abilities-inspector' )
		);
		$views['disabled'] = sprintf(
			'<a href="%s" class="%s">%s</a>',
			esc_url( add_query_arg( 'status', 'disabled', $base_url ) ),
			$status === 'disabled' ? 'current' : '',
			esc_html__( 'Disabled', 'abilities-inspector' )
		);

		return $views;
	}

	protected function extra_tablenav( $which ) {
		if ( $which !== 'top' ) return;

		$categories = function_exists( 'wp_get_ability_categories' ) ? wp_get_ability_categories() : array();
		$current = isset( $_GET['category'] ) ? sanitize_text_field( wp_unslash( $_GET['category'] ) ) : '';
		?>
		<div class="alignleft actions abin-actions">
			<label for="abin-category" class="screen-reader-text"><?php esc_html_e( 'Filter by category', 'abilities-inspector' ); ?></label>
			<select name="category" id="abin-category">
				<option value=""><?php esc_html_e( 'All categories', 'abilities-inspector' ); ?></option>
				<?php
				if ( is_array( $categories ) ) :
					foreach ( $categories as $slug => $cat ) :
						$label = $slug;
						if ( is_object( $cat ) && method_exists( $cat, 'get_label' ) ) {
							$label = $cat->get_label();
						} elseif ( is_object( $cat ) && isset( $cat->label ) ) {
							$label = $cat->label;
						}
						printf(
							'<option value="%s" %s>%s</option>',
							esc_attr( $slug ),
							selected( $current, $slug, false ),
							esc_html( $label )
						);
					endforeach;
				endif;
				?>
			</select>
			<?php submit_button( __( 'Filter', 'abilities-inspector' ), 'secondary', false, false ); ?>
		</div>
		<?php
	}

	public function display() {
		// Add our nonce to the list table form (which is the outer <form method="get"> in the page).
		echo '<input type="hidden" name="abin_nonce" value="' . esc_attr( wp_create_nonce( 'abin_action' ) ) . '" />';
		parent::display();
	}

	public function column_name( $item ) {
		$name = $item['name'];
		$label = $item['label'] ?: $name;

		$desc = $item['description'] ? '<div class="abin-muted abin-trim">' . esc_html( $item['description'] ) . '</div>' : '';

		$title = sprintf(
			'<div class="abin-title"><code>%s</code></div><div class="abin-label">%s</div>%s',
			esc_html( $name ),
			esc_html( $label ),
			$desc
		);

		return $title;
	}

	public function column_category( $item ) {
		return $item['category_label'] ? esc_html( $item['category_label'] ) : '<span class="abin-muted">—</span>';
	}

	public function column_executions( $item ) {
		return '<code>' . esc_html( number_format_i18n( (int) $item['executions'] ) ) . '</code>';
	}

	public function column_status( $item ) {
		return ! empty( $item['disabled'] )
			? '<span class="abin-chip abin-chip--warn">' . esc_html__( 'Disabled', 'abilities-inspector' ) . '</span>'
			: '<span class="abin-chip abin-chip--ok">' . esc_html__( 'Enabled', 'abilities-inspector' ) . '</span>';
	}

	public function column_details( $item ) {
		$base = remove_query_arg( array( 'action', 'ability', 'paged' ) );
		if ( ! empty( $item['disabled'] ) ) {
			$toggle_label = __( 'Enable', 'abilities-inspector' );
			$toggle_url = add_query_arg( array(
				'action' => 'enable',
				'ability' => rawurlencode( $item['name'] ),
			), $base );
		} else {
			$toggle_label = __( 'Disable', 'abilities-inspector' );
			$toggle_url = add_query_arg( array(
				'action' => 'disable',
				'ability' => rawurlencode( $item['name'] ),
			), $base );
		}
		$toggle_url = wp_nonce_url( $toggle_url, 'abin_action', 'abin_nonce' );
		$toggle_class = ! empty( $item['disabled'] ) ? 'button button-small abin-toggle' : 'button button-small abin-toggle abin-danger';

		// Button toggles a hidden details row in JS.
		$data = array(
			'name' => $item['name'],
			'label' => $item['label'],
			'description' => $item['description'],
			'category' => $item['category'],
			'category_label' => $item['category_label'],
			'show_in_rest' => $item['show_in_rest'],
			'disabled' => $item['disabled'],
			'executions' => $item['executions'],
			'annotations' => $item['annotations'],
			'input_schema' => $item['input_schema'],
			'output_schema' => $item['output_schema'],
		);

		$json = wp_json_encode( $data );
		$details_button = sprintf(
			'<button type="button" class="button button-small abin-details" data-ability="%s">%s <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button>',
			esc_attr( base64_encode( $json ) ),
			esc_html__( 'Details', 'abilities-inspector' )
		);
		$toggle_link = sprintf(
			'<a href="%s" class="%s">%s</a>',
			esc_url( $toggle_url ),
			esc_attr( $toggle_class ),
			esc_html( $toggle_label )
		);

		return $toggle_link . ' ' . $details_button;
	}

	public function single_row( $item ) {
		parent::single_row( $item );

		// Render a hidden "details" row right after each item row.
		echo '<tr class="abin-details-row" style="display:none;">';
		echo '<td colspan="' . esc_attr( count( $this->get_columns() ) ) . '">';
		echo '<div class="abin-details-panel"></div>';
		echo '</td></tr>';
	}
}
