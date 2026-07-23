<?php
/**
 * Atx Nav Menu - Export / Import
 *
 * Admin page + WP-CLI for exporting and importing the mega nav menu
 * with all items, hierarchy, meta, and images.
 *
 * WP-CLI:
 *   wp eval-file <path>/atx-nav-menu-export.php export
 *   wp eval-file <path>/atx-nav-menu-export.php import [path-to-json]
 *
 * @package Atx
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atx_Nav_Menu_Export {

	const MENU_LOCATION = 'primary-v2';
	const EXPORT_DIR    = '/assets/exports/';
	const DEFAULTS_OPTION = 'atx_nav_menu_default_exports';

	private static $meta_keys = array(
		'_atx_nav_icon',
		'_atx_nav_icon_custom',
		'_atx_nav_slider_enabled',
		'_atx_nav_slider_items',
		'_atx_nav_brands_enabled',
		'_atx_nav_brand_items',
		'new_tag',
		'_new_tag',
		'custom_text',
		'_custom_text',
	);

	/**
	 * Log buffer for AJAX responses
	 */
	private static $log_buffer = array();

	public function __construct() {
		// Hidden admin page (no menu item — accessed via direct URL only)
		add_action( 'admin_menu', array( $this, 'add_admin_page' ) );
		add_action( 'admin_init', array( $this, 'redirect_legacy_admin_url' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_atx_nav_export', array( $this, 'ajax_export' ) );
		add_action( 'wp_ajax_atx_nav_import', array( $this, 'ajax_import' ) );
		add_action( 'wp_ajax_atx_nav_import_file', array( $this, 'ajax_import_file' ) );
		add_action( 'wp_ajax_atx_nav_import_default', array( $this, 'ajax_import_default' ) );
		add_action( 'wp_ajax_atx_nav_set_default_export', array( $this, 'ajax_set_default_export' ) );
		add_action( 'wp_ajax_atx_nav_delete_export', array( $this, 'ajax_delete_export' ) );
		add_action( 'wp_ajax_atx_nav_reset_baseline', array( $this, 'ajax_reset_baseline' ) );
		add_action( 'wp_ajax_atx_nav_download', array( $this, 'ajax_download' ) );

		// Add export button on nav-menus.php
		add_action( 'admin_footer-nav-menus.php', array( $this, 'inject_export_button' ) );
	}

	/**
	 * Redirect the old admin.php route to the registered Appearance page.
	 */
	public function redirect_legacy_admin_url() {
		global $pagenow;

		if ( $pagenow === 'admin.php' && ( $_GET['page'] ?? '' ) === 'atx-nav-export' ) {
			wp_safe_redirect( admin_url( 'themes.php?page=atx-nav-export' ) );
			exit;
		}
	}

	/**
	 * Add admin page under Appearance.
	 */
	public function add_admin_page() {
		add_submenu_page(
			'themes.php',
			__( 'Nav Export/Import', 'atx_theme' ),
			__( 'Nav Export/Import', 'atx_theme' ),
			Atx_Nav_Menu_Config::get( 'capability' ),
			'atx-nav-export',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Inject an "Export / Import" button on the nav-menus.php page
	 */
	public function inject_export_button() {
		$locations_json = wp_json_encode( array_flip( get_nav_menu_locations() ) );
		?>
		<script>
		jQuery(function($) {
			let menuLocationsById = <?= $locations_json ?: '{}'; ?>;
			let urlMenu = new URLSearchParams(window.location.search).get('menu');
			let formMenu = $('input[name="menu"]').val() || $('#menu').val() || $('select#menu').val();
			let selectedMenu = parseInt(urlMenu || formMenu || 0, 10);
			let selectedLocation = menuLocationsById[selectedMenu] || '';
			let locationParam = selectedLocation ? '&menu_location=' + encodeURIComponent(selectedLocation) : '';

			let exportUrl = '<?= esc_js( admin_url( 'themes.php?page=atx-nav-export' ) ); ?>' + locationParam;
			let builderUrl = '<?= esc_js( admin_url( 'themes.php?page=atx-visual-builder' ) ); ?>' + locationParam;

			let $target = $('#nav-menu-header .major-publishing-actions');
			if (!$target.length) $target = $('.publishing-action');
			if (!$target.length) $target = $('#update-nav-menu').find('input[type="submit"]').first().parent();

			if ($target.find('.atx-admin-builder-link').length) return;

			if ($target.length) {
				$target.prepend(
					'<a href="' + builderUrl + '" class="button atx-admin-builder-link" style="margin-right:8px;">Visual Builder</a>' +
					'<a href="' + exportUrl + '" class="button atx-admin-export-link" style="margin-right:8px;">Export / Import</a>'
				);
			}
		});
		</script>
		<?php
	}

	/**
	 * Enqueue JS on our admin page
	 */
	public function enqueue_assets( $hook ) {
		if ( $hook !== 'appearance_page_atx-nav-export' ) {
			return;
		}

		wp_enqueue_style(
			'atx-nav-admin-css',
			Atx_Nav_Menu::get_module_url() . '/assets/css/admin/atx-nav-menu-admin.css',
			array(),
			Atx_Nav_Menu::VERSION
		);

		wp_enqueue_script(
			'atx-nav-admin-export',
			Atx_Nav_Menu::get_module_url() . '/assets/js/admin/export.js',
			array( 'jquery' ),
			Atx_Nav_Menu::VERSION,
			true
		);
		wp_localize_script( 'atx-nav-admin-export', 'atxNavAdmin', array(
			'nonce' => wp_create_nonce( 'atx_nav_admin' ),
		) );
	}

	/**
	 * Render the admin page using template
	 */
	public function render_admin_page() {
		$exports = self::get_export_list();
		echo Atx_Nav_Menu::get_template( 'admin/export-page', array(
			'exports'          => $exports,
			'current_location' => self::get_requested_menu_location(),
			'default_exports'  => self::get_default_exports(),
		), false );
	}

	// ── AJAX Handlers ──

	public function ajax_export() {
		check_ajax_referer( 'atx_nav_admin', '_wpnonce' );
		if ( ! current_user_can( Atx_Nav_Menu_Config::get( 'capability' ) ) ) wp_send_json_error( 'Permission denied.' );

		$filepath = self::export( self::get_requested_menu_location() );
		if ( $filepath ) {
			$nonce = wp_create_nonce( 'atx_nav_admin' );
			$url = admin_url( 'admin-ajax.php?action=atx_nav_download&file=' . urlencode( basename( $filepath ) ) . '&_wpnonce=' . $nonce );
			wp_send_json_success( array( 'url' => $url, 'log' => self::$log_buffer ) );
		} else {
			wp_send_json_error( 'Export failed.' );
		}
	}

	public function ajax_import() {
		check_ajax_referer( 'atx_nav_admin', '_wpnonce' );
		if ( ! current_user_can( Atx_Nav_Menu_Config::get( 'capability' ) ) ) wp_send_json_error( 'Permission denied.' );

		$json = wp_unslash( $_POST['json_data'] ?? '' );
		if ( empty( $json ) ) wp_send_json_error( 'No data received.' );

		// Validate JSON before writing
		$parsed = json_decode( $json, true );
		if ( json_last_error() !== JSON_ERROR_NONE || empty( $parsed['items'] ) ) {
			wp_send_json_error( 'Invalid JSON data.' );
		}

		// Save to temp file with random name and import
		$tmp = Atx_Nav_Menu::get_module_path() . self::EXPORT_DIR . 'tmp-import-' . wp_generate_password( 12, false ) . '.json';
		file_put_contents( $tmp, $json );
		$result = self::import( $tmp, self::get_requested_menu_location() );
		@unlink( $tmp );

		if ( $result ) {
			wp_send_json_success( array( 'log' => self::$log_buffer ) );
		} else {
			wp_send_json_error( 'Import failed.' );
		}
	}

	public function ajax_import_file() {
		check_ajax_referer( 'atx_nav_admin', '_wpnonce' );
		if ( ! current_user_can( Atx_Nav_Menu_Config::get( 'capability' ) ) ) wp_send_json_error( 'Permission denied.' );

		$filepath = sanitize_text_field( $_POST['filepath'] ?? '' );
		$exports_dir = realpath( Atx_Nav_Menu::get_module_path() . self::EXPORT_DIR );

		// Validate filepath is within the exports directory (prevent path traversal)
		if ( empty( $filepath ) || ! file_exists( $filepath ) ) wp_send_json_error( 'File not found.' );
		$real_filepath = realpath( $filepath );
		if ( ! $real_filepath || strpos( $real_filepath, $exports_dir ) !== 0 ) {
			wp_send_json_error( 'Invalid file path.' );
		}
		$filepath = $real_filepath;

		$result = self::import( $filepath, self::get_requested_menu_location() );
		if ( $result ) {
			wp_send_json_success( array( 'log' => self::$log_buffer ) );
		} else {
			wp_send_json_error( 'Import failed.' );
		}
	}

	public function ajax_import_default() {
		check_ajax_referer( 'atx_nav_admin', '_wpnonce' );
		if ( ! current_user_can( Atx_Nav_Menu_Config::get( 'capability' ) ) ) wp_send_json_error( 'Permission denied.' );

		$menu_location = self::get_requested_menu_location();
		$defaults      = self::get_default_exports();
		$filename      = $defaults[ $menu_location ] ?? '';
		if ( empty( $filename ) ) wp_send_json_error( 'No default export set for this menu location.' );

		$filepath = self::resolve_export_filename( $filename );
		if ( ! $filepath ) wp_send_json_error( 'Default export file not found.' );

		$result = self::import( $filepath, $menu_location );
		if ( $result ) {
			wp_send_json_success( array( 'log' => self::$log_buffer ) );
		} else {
			wp_send_json_error( 'Import failed.' );
		}
	}

	public function ajax_set_default_export() {
		check_ajax_referer( 'atx_nav_admin', '_wpnonce' );
		if ( ! current_user_can( Atx_Nav_Menu_Config::get( 'capability' ) ) ) wp_send_json_error( 'Permission denied.' );

		$menu_location = self::get_requested_menu_location();
		$filename      = sanitize_file_name( wp_unslash( $_POST['file'] ?? '' ) );
		if ( ! self::resolve_export_filename( $filename ) ) wp_send_json_error( 'Export file not found.' );

		$defaults = self::get_default_exports();
		$defaults[ $menu_location ] = $filename;
		update_option( self::DEFAULTS_OPTION, $defaults, false );

		wp_send_json_success( array( 'defaults' => $defaults ) );
	}

	public function ajax_delete_export() {
		check_ajax_referer( 'atx_nav_admin', '_wpnonce' );
		if ( ! current_user_can( Atx_Nav_Menu_Config::get( 'capability' ) ) ) wp_send_json_error( 'Permission denied.' );

		$filename = sanitize_file_name( wp_unslash( $_POST['file'] ?? '' ) );
		$defaults = self::get_default_exports();
		$used_by = array_keys( array_filter( $defaults, function( $default_file ) use ( $filename ) {
			return $default_file === $filename;
		} ) );

		if ( ! empty( $used_by ) ) {
			wp_send_json_error( 'This export is a default for: ' . implode( ', ', $used_by ) . '.' );
		}

		$filepath = self::resolve_export_filename( $filename );
		if ( ! $filepath || basename( $filepath ) === 'baseline.json' ) wp_send_json_error( 'Export file not found.' );

		if ( ! @unlink( $filepath ) ) wp_send_json_error( 'Could not delete export.' );

		wp_send_json_success();
	}

	// ── AJAX: Reset to baseline ──

	public function ajax_reset_baseline() {
		check_ajax_referer( 'atx_nav_admin', '_wpnonce' );
		if ( ! current_user_can( Atx_Nav_Menu_Config::get( 'capability' ) ) ) wp_send_json_error( 'Permission denied.' );
		if ( self::get_requested_menu_location() !== self::MENU_LOCATION ) wp_send_json_error( 'Baseline reset is only available for the V2 mega menu.' );

		$baseline = self::get_baseline_path();
		if ( ! $baseline || ! file_exists( $baseline ) ) {
			wp_send_json_error( 'Baseline file not found.' );
		}

		$result = self::import( $baseline, self::get_requested_menu_location() );
		if ( $result ) {
			wp_send_json_success( array( 'log' => self::$log_buffer ) );
		} else {
			wp_send_json_error( 'Reset failed.' );
		}
	}

	/**
	 * AJAX: Secure file download (since .htaccess blocks direct access)
	 */
	public function ajax_download() {
		check_ajax_referer( 'atx_nav_admin', '_wpnonce' );
		if ( ! current_user_can( Atx_Nav_Menu_Config::get( 'capability' ) ) ) wp_die( 'Permission denied.' );

		$file = sanitize_text_field( $_GET['file'] ?? '' );
		$exports_dir = realpath( Atx_Nav_Menu::get_module_path() . self::EXPORT_DIR );
		$filepath = realpath( $exports_dir . '/' . basename( $file ) );

		if ( ! $filepath || strpos( $filepath, $exports_dir ) !== 0 || ! file_exists( $filepath ) ) {
			wp_die( 'File not found.' );
		}

		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="' . basename( $filepath ) . '"' );
		header( 'Content-Length: ' . filesize( $filepath ) );
		readfile( $filepath );
		exit;
	}

	/**
	 * Get the path to the baseline (default) JSON file
	 */
	public static function get_baseline_path() {
		$path = Atx_Nav_Menu::get_module_path() . self::EXPORT_DIR . 'baseline.json';
		return file_exists( $path ) ? $path : false;
	}

	// ── Core Export Logic ──

	public static function export( $menu_location = self::MENU_LOCATION ) {
		$locations = get_nav_menu_locations();
		if ( empty( $locations[ $menu_location ] ) ) {
			self::log( 'No menu assigned to ' . $menu_location );
			return false;
		}

		$menu_id    = $locations[ $menu_location ];
		$menu_obj   = wp_get_nav_menu_object( $menu_id );
		$menu_items = wp_get_nav_menu_items( $menu_id, array( 'update_post_term_cache' => false ) );

		if ( empty( $menu_items ) ) {
			self::log( 'Menu is empty.' );
			return false;
		}

		self::log( 'Exporting: ' . $menu_obj->name . ' (' . count( $menu_items ) . ' items)' );

		$export = array(
			'version'     => '1.0',
			'exported_at' => current_time( 'mysql' ),
			'menu_location' => $menu_location,
			'menu_name'   => $menu_obj->name,
			'items'       => array(),
			'images'      => array(),
		);

		$exported_images = array();

		foreach ( $menu_items as $item ) {
			$item_data = array(
				'id'        => $item->ID,
				'title'     => $item->title,
				'url'       => $item->url,
				'parent_id' => intval( $item->menu_item_parent ),
				'position'  => $item->menu_order,
				'classes'   => array_filter( (array) $item->classes ),
				'type'      => $item->type,
				'object'    => $item->object,
				'object_id' => $item->object_id,
				'meta'      => array(),
			);

			foreach ( self::get_export_meta_keys() as $key ) {
				$value = get_post_meta( $item->ID, $key, true );
				if ( $value !== '' && $value !== false ) {
					$item_data['meta'][ $key ] = $value;
					self::collect_image_ids( $key, $value, $exported_images );
					self::collect_acf_image_ids( $key, $value, $exported_images );
				}
			}

			$export['items'][] = $item_data;
		}

		foreach ( $exported_images as $img_id => $true ) {
			$file_path = get_attached_file( $img_id );
			if ( $file_path && file_exists( $file_path ) ) {
				$export['images'][ $img_id ] = array(
					'filename' => basename( $file_path ),
					'mime'     => get_post_mime_type( $img_id ),
					'title'    => get_the_title( $img_id ),
					'data'     => base64_encode( file_get_contents( $file_path ) ),
				);
				self::log( '  + Image: ' . basename( $file_path ) );
			}
		}

		$export_dir = Atx_Nav_Menu::get_module_path() . self::EXPORT_DIR;
		wp_mkdir_p( $export_dir );

		$filename = sanitize_file_name( 'atx-nav-export-' . $menu_location . '-' . $menu_obj->name . '-' . date( 'Y-m-d-His' ) . '.json' );
		$filepath = $export_dir . $filename;

		file_put_contents( $filepath, wp_json_encode( $export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );

		self::log( 'Saved: ' . $filename );
		self::log( 'Items: ' . count( $export['items'] ) . ' | Images: ' . count( $export['images'] ) );

		return $filepath;
	}

	// ── Core Import Logic ──

	public static function import( $filepath = '', $menu_location = self::MENU_LOCATION ) {
		if ( empty( $filepath ) ) {
			$filepath = self::get_latest_export_path();
		}

		if ( ! $filepath || ! file_exists( $filepath ) ) {
			self::log( 'Export file not found.' );
			return false;
		}

		$data = json_decode( file_get_contents( $filepath ), true );
		if ( empty( $data['items'] ) ) {
			self::log( 'Invalid or empty export.' );
			return false;
		}

		$menu_location = ! empty( $menu_location ) ? sanitize_key( $menu_location ) : sanitize_key( $data['menu_location'] ?? self::MENU_LOCATION );

		self::log( 'Importing: ' . basename( $filepath ) );

		$locations   = get_theme_mod( 'nav_menu_locations', array() );
		$old_menu_id = absint( $locations[ $menu_location ] ?? 0 );
		$menu_name   = self::unique_import_menu_name( $data['menu_name'] ?? 'Imported Nav', $menu_location );

		$menu_id = wp_create_nav_menu( $menu_name );
		if ( is_wp_error( $menu_id ) ) {
			self::log( 'Error: ' . $menu_id->get_error_message() );
			return false;
		}

		$locations[ $menu_location ] = $menu_id;
		set_theme_mod( 'nav_menu_locations', $locations );
		self::log( 'Created menu (ID: ' . $menu_id . ')' );

		if ( $old_menu_id && ! in_array( $old_menu_id, array_map( 'absint', $locations ), true ) ) {
			wp_delete_nav_menu( $old_menu_id );
			self::log( 'Deleted previous menu for location: ' . $menu_location );
		}

		// Import images
		$image_map = array();
		if ( ! empty( $data['images'] ) ) {
			foreach ( $data['images'] as $old_id => $img ) {
				$new_id = self::import_image( $img );
				if ( $new_id ) {
					$image_map[ $old_id ] = $new_id;
				}
			}
			self::log( 'Images: ' . count( $image_map ) . ' imported' );
		}

		// Import items — process parents before children using recursive ordering
		$item_map = array();
		$items    = $data['items'];
		$ordered  = self::order_items_by_hierarchy( $items );

		foreach ( $ordered as $item ) {
			$parent_id = 0;
			if ( $item['parent_id'] && isset( $item_map[ $item['parent_id'] ] ) ) {
				$parent_id = $item_map[ $item['parent_id'] ];
			}

			$new_item_id = wp_update_nav_menu_item( $menu_id, 0, array(
				'menu-item-title'     => $item['title'],
				'menu-item-url'       => $item['url'],
				'menu-item-status'    => 'publish',
				'menu-item-type'      => $item['type'] ?? 'custom',
				'menu-item-object'    => $item['object'] ?? '',
				'menu-item-object-id' => $item['object_id'] ?? 0,
				'menu-item-parent-id' => $parent_id,
				'menu-item-position'  => $item['position'],
				'menu-item-classes'   => implode( ' ', $item['classes'] ?? array() ),
			) );

			if ( is_wp_error( $new_item_id ) ) {
				self::log( '  ! ' . $item['title'] . ': ' . $new_item_id->get_error_message() );
				continue;
			}

			$item_map[ $item['id'] ] = $new_item_id;

			if ( ! empty( $item['meta'] ) ) {
				foreach ( $item['meta'] as $key => $value ) {
					$value = self::remap_image_ids( $key, $value, $image_map );
					$value = self::remap_acf_image_ids( $key, $value, $image_map );
					update_post_meta( $new_item_id, $key, $value );
				}
			}

			self::log( '  + ' . $item['title'] );
		}

		self::log( 'Done! ' . count( $item_map ) . ' items imported.' );
		return $menu_id;
	}

	// ── Helpers ──

	private static function collect_image_ids( $key, $value, &$collected ) {
		if ( $key === '_atx_nav_icon_custom' && $value ) {
			$collected[ intval( $value ) ] = true;
		}
		if ( $key === '_atx_nav_slider_items' && is_array( $value ) ) {
			foreach ( $value as $slide ) {
				if ( ! empty( $slide['image'] ) ) $collected[ intval( $slide['image'] ) ] = true;
			}
		}
		if ( $key === '_atx_nav_brand_items' && is_array( $value ) ) {
			foreach ( $value as $brand ) {
				if ( ! empty( $brand['logo'] ) ) $collected[ intval( $brand['logo'] ) ] = true;
			}
		}
	}

	private static function collect_acf_image_ids( $key, $value, &$collected ) {
		$type = self::get_acf_field_type_by_name( $key );
		if ( in_array( $type, array( 'image', 'file' ), true ) && $value ) {
			$collected[ intval( $value ) ] = true;
		}

		if ( $type === 'gallery' && is_array( $value ) ) {
			foreach ( $value as $image_id ) {
				if ( $image_id ) {
					$collected[ intval( $image_id ) ] = true;
				}
			}
		}
	}

	private static function get_export_meta_keys() {
		$keys = self::$meta_keys;

		if ( function_exists( 'acf_get_field_groups' ) && function_exists( 'acf_get_fields' ) ) {
			foreach ( acf_get_field_groups() as $group ) {
				foreach ( (array) ( $group['location'] ?? array() ) as $rules ) {
					foreach ( (array) $rules as $rule ) {
						if ( ( $rule['param'] ?? '' ) !== 'nav_menu_item' ) {
							continue;
						}

						foreach ( (array) acf_get_fields( $group ) as $field ) {
							if ( empty( $field['name'] ) ) {
								continue;
							}
							$keys[] = $field['name'];
							$keys[] = '_' . $field['name'];
						}
					}
				}
			}
		}

		return array_values( array_unique( apply_filters( 'atx_nav_export_meta_keys', $keys ) ) );
	}

	private static function remap_image_ids( $key, $value, $map ) {
		if ( $key === '_atx_nav_icon_custom' && $value ) {
			return $map[ intval( $value ) ] ?? $value;
		}
		if ( $key === '_atx_nav_slider_items' && is_array( $value ) ) {
			foreach ( $value as &$slide ) {
				if ( ! empty( $slide['image'] ) && isset( $map[ intval( $slide['image'] ) ] ) ) {
					$slide['image'] = $map[ intval( $slide['image'] ) ];
				}
			}
			return $value;
		}
		if ( $key === '_atx_nav_brand_items' && is_array( $value ) ) {
			foreach ( $value as &$brand ) {
				if ( ! empty( $brand['logo'] ) && isset( $map[ intval( $brand['logo'] ) ] ) ) {
					$brand['logo'] = $map[ intval( $brand['logo'] ) ];
				}
			}
			return $value;
		}
		return $value;
	}

	private static function remap_acf_image_ids( $key, $value, $map ) {
		$type = self::get_acf_field_type_by_name( $key );
		if ( in_array( $type, array( 'image', 'file' ), true ) && $value ) {
			return $map[ intval( $value ) ] ?? $value;
		}

		if ( $type === 'gallery' && is_array( $value ) ) {
			return array_map( function( $image_id ) use ( $map ) {
				return $map[ intval( $image_id ) ] ?? $image_id;
			}, $value );
		}

		return $value;
	}

	private static function get_acf_field_type_by_name( $name ) {
		static $types = null;
		if ( $types !== null ) {
			return $types[ $name ] ?? '';
		}

		$types = array();
		if ( function_exists( 'acf_get_field_groups' ) && function_exists( 'acf_get_fields' ) ) {
			foreach ( acf_get_field_groups() as $group ) {
				foreach ( (array) ( $group['location'] ?? array() ) as $rules ) {
					foreach ( (array) $rules as $rule ) {
						if ( ( $rule['param'] ?? '' ) !== 'nav_menu_item' ) {
							continue;
						}

						foreach ( (array) acf_get_fields( $group ) as $field ) {
							if ( ! empty( $field['name'] ) ) {
								$types[ $field['name'] ] = $field['type'] ?? '';
							}
						}
					}
				}
			}
		}

		return $types[ $name ] ?? '';
	}

	private static function import_image( $img ) {
		$existing = get_posts( array(
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'meta_key'    => '_atx_nav_imported_file',
			'meta_value'  => $img['filename'],
			'numberposts' => 1,
		) );
		if ( ! empty( $existing ) ) return $existing[0]->ID;

		$decoded = base64_decode( $img['data'] );
		if ( ! $decoded ) return 0;

		$upload = wp_upload_bits( $img['filename'], null, $decoded );
		if ( ! empty( $upload['error'] ) ) return 0;

		$filetype   = wp_check_filetype( $upload['file'] );
		$attach_id  = wp_insert_attachment( array(
			'post_mime_type' => $filetype['type'] ?: $img['mime'],
			'post_title'     => $img['title'] ?: pathinfo( $img['filename'], PATHINFO_FILENAME ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		), $upload['file'] );

		if ( is_wp_error( $attach_id ) ) return 0;

		update_post_meta( $attach_id, '_atx_nav_imported_file', $img['filename'] );
		return $attach_id;
	}

	private static function get_export_list() {
		$dir   = Atx_Nav_Menu::get_module_path() . self::EXPORT_DIR;
		$nonce = wp_create_nonce( 'atx_nav_admin' );
		$files = glob( $dir . 'atx-nav-export-*.json' );
		if ( empty( $files ) ) return array();

		usort( $files, function( $a, $b ) { return filemtime( $b ) - filemtime( $a ); } );

		$defaults = self::get_default_exports();
		return array_map( function( $f ) use ( $nonce, $defaults ) {
			$filename = basename( $f );
			$data = json_decode( file_get_contents( $f ), true );
			$default_for = array_keys( array_filter( $defaults, function( $default_file ) use ( $filename ) {
				return $default_file === $filename;
			} ) );
			$download_url = admin_url( 'admin-ajax.php?action=atx_nav_download&file=' . urlencode( basename( $f ) ) . '&_wpnonce=' . $nonce );
			return array(
				'name' => $filename,
				'path' => $f,
				'url'  => $download_url,
				'date' => date( 'Y-m-d H:i', filemtime( $f ) ),
				'size' => size_format( filesize( $f ) ),
				'menu_name' => $data['menu_name'] ?? '',
				'menu_location' => $data['menu_location'] ?? '',
				'default_for' => $default_for,
				'can_delete' => empty( $default_for ),
			);
		}, $files );
	}

	private static function get_default_exports() {
		$defaults = get_option( self::DEFAULTS_OPTION, array() );
		return is_array( $defaults ) ? array_map( 'sanitize_file_name', $defaults ) : array();
	}

	private static function resolve_export_filename( $filename ) {
		$exports_dir = realpath( Atx_Nav_Menu::get_module_path() . self::EXPORT_DIR );
		if ( ! $exports_dir || empty( $filename ) ) return false;

		$filepath = realpath( $exports_dir . '/' . basename( $filename ) );
		if ( ! $filepath || strpos( $filepath, $exports_dir ) !== 0 || ! file_exists( $filepath ) ) {
			return false;
		}

		return $filepath;
	}

	private static function unique_import_menu_name( $base_name, $menu_location ) {
		$base = trim( sanitize_text_field( $base_name ) );
		if ( $base === '' ) {
			$base = 'Imported Nav';
		}

		$name = $base . ' - ' . $menu_location;
		$try = $name;
		$i = 2;
		while ( wp_get_nav_menu_object( $try ) ) {
			$try = $name . ' ' . $i++;
		}

		return $try;
	}

	/**
	 * Order items so parents always come before their children.
	 * Preserves position ordering within each level.
	 */
	private static function order_items_by_hierarchy( $items ) {
		// Index by id
		$by_parent = array();
		foreach ( $items as $item ) {
			$pid = $item['parent_id'] ?: 0;
			$by_parent[ $pid ][] = $item;
		}

		// Sort each group by position
		foreach ( $by_parent as &$group ) {
			usort( $group, function( $a, $b ) {
				return $a['position'] - $b['position'];
			} );
		}

		// Flatten: start with root items, then recursively add children
		$result = array();
		self::flatten_hierarchy( $by_parent, 0, $result );
		return $result;
	}

	private static function flatten_hierarchy( &$by_parent, $parent_id, &$result ) {
		if ( empty( $by_parent[ $parent_id ] ) ) return;

		foreach ( $by_parent[ $parent_id ] as $item ) {
			$result[] = $item;
			self::flatten_hierarchy( $by_parent, $item['id'], $result );
		}
	}

	private static function get_latest_export_path() {
		$list = self::get_export_list();
		return ! empty( $list ) ? $list[0]['path'] : false;
	}

	private static function get_requested_menu_location() {
		$registered = get_registered_nav_menus();
		$requested  = sanitize_key( $_REQUEST['menu_location'] ?? self::MENU_LOCATION );

		return isset( $registered[ $requested ] ) ? $requested : self::MENU_LOCATION;
	}

	private static function log( $message ) {
		self::$log_buffer[] = $message;
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::log( $message );
		}
	}
}

// ── WP-CLI runner ──
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	$argv          = $GLOBALS['argv'] ?? array();
	$is_eval_file  = ( $argv[1] ?? '' ) === 'eval-file';
	$requested     = isset( $argv[2] ) ? realpath( (string) $argv[2] ) : false;
	$is_this_file  = $is_eval_file && $requested && $requested === realpath( __FILE__ );

	if ( $is_this_file ) {
		$action = $argv[3] ?? '';

		if ( $action === 'export' ) {
			Atx_Nav_Menu_Export::export();
		} elseif ( $action === 'import' ) {
			Atx_Nav_Menu_Export::import( $argv[4] ?? '' );
		} else {
			WP_CLI::log( 'Usage: wp eval-file <path>/atx-nav-menu-export.php [export|import] [file]' );
		}
	}
}
