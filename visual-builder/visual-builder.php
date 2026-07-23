<?php
/**
 * Atx Nav Menu - Visual Builder
 *
 * A visual drag-and-drop builder for the mega navigation.
 * Split-screen: tree editor on left, live preview on right.
 *
 * @package Atx
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Atx_Nav_Builder_Preview_Walker' ) ) {
	class Atx_Nav_Builder_Preview_Walker extends Walker_Nav_Menu {
		public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
			$indent  = $depth ? str_repeat( "\t", $depth ) : '';
			$classes = empty( $item->classes ) ? array() : (array) $item->classes;
			$output .= $indent . '<li class="' . esc_attr( implode( ' ', array_filter( $classes ) ) ) . '" data-item-id="' . esc_attr( $item->ID ) . '">';

			$atts = array(
				'href' => ! empty( $item->url ) ? $item->url : '#',
			);

			$output .= '<a href="' . esc_url( $atts['href'] ) . '">' . esc_html( $item->title ) . '</a>';
		}
	}
}

class Atx_Nav_Visual_Builder {

	const MENU_LOCATION = 'primary-v2';
	const PREVIEW_ACTION = 'atx_vb_preview_page';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_page' ) );
		add_action( 'admin_init', array( $this, 'redirect_legacy_admin_url' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'template_redirect', array( $this, 'prepare_frontend_preview' ) );
		add_action( 'wp_head', array( $this, 'output_frontend_preview_suppression_styles' ), PHP_INT_MAX );
		add_action( 'wp_footer', array( $this, 'output_frontend_preview_bridge' ), PHP_INT_MAX );
		add_filter( 'wp_get_nav_menu_items', array( $this, 'filter_frontend_preview_items' ), PHP_INT_MAX, 3 );
		add_filter( 'wp_nav_menu', array( $this, 'mark_frontend_preview_menu' ), PHP_INT_MAX, 2 );
		add_filter( 'acf/load_value', array( $this, 'filter_frontend_preview_acf_value' ), PHP_INT_MAX, 3 );
		add_filter( 'get_post_metadata', array( $this, 'filter_frontend_preview_meta' ), PHP_INT_MAX, 5 );
		add_action( 'wp_ajax_' . self::PREVIEW_ACTION, array( $this, 'render_preview_page' ) );
		add_action( 'wp_ajax_atx_vb_load', array( $this, 'ajax_load' ) );
		add_action( 'wp_ajax_atx_vb_search_items', array( $this, 'ajax_search_items' ) );
		add_action( 'wp_ajax_atx_vb_save', array( $this, 'ajax_save' ) );
		add_action( 'wp_ajax_atx_vb_add_item', array( $this, 'ajax_add_item' ) );
		add_action( 'wp_ajax_atx_vb_delete_item', array( $this, 'ajax_delete_item' ) );
		add_action( 'wp_ajax_atx_vb_preview_sync', array( $this, 'ajax_preview_sync' ) );
		add_action( 'wp_ajax_atx_vb_render_nav', array( $this, 'ajax_render_nav' ) );
		add_action( 'wp_ajax_atx_vb_get_extras', array( $this, 'ajax_get_extras' ) );
		add_action( 'wp_ajax_atx_vb_save_extras', array( $this, 'ajax_save_extras' ) );
		add_action( 'wp_ajax_atx_vb_save_custom_icon', array( $this, 'ajax_save_custom_icon' ) );
		add_action( 'wp_ajax_atx_vb_health_check', array( $this, 'ajax_health_check' ) );
		add_action( 'wp_ajax_atx_vb_revisions', array( $this, 'ajax_revisions' ) );
		add_action( 'wp_ajax_atx_vb_copy_items', array( $this, 'ajax_copy_items' ) );
	}

	/**
	 * Redirect the old admin.php route to the registered Appearance page.
	 */
	public function redirect_legacy_admin_url() {
		global $pagenow;

		if ( $pagenow === 'admin.php' && ( $_GET['page'] ?? '' ) === 'atx-visual-builder' ) {
			wp_safe_redirect( admin_url( 'themes.php?page=atx-visual-builder' ) );
			exit;
		}
	}

	/**
	 * Admin page under Appearance.
	 */
	public function add_admin_page() {
		add_submenu_page(
			'themes.php',
			__( 'Visual Nav Builder', 'atx_theme' ),
			__( 'Visual Nav Builder', 'atx_theme' ),
			Atx_Nav_Menu_Config::get( 'capability' ),
			'atx-visual-builder',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue builder assets
	 */
	public function enqueue_assets( $hook ) {
		if ( $hook !== 'appearance_page_atx-visual-builder' ) {
			return;
		}

		$base = Atx_Nav_Menu::get_module_url() . '/visual-builder';
		$ver  = Atx_Nav_Menu::VERSION;
		$current_location = self::get_requested_menu_location();

		// jQuery UI for drag/drop
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script( 'jquery-ui-draggable' );
		wp_enqueue_script( 'jquery-ui-droppable' );

		$style_deps = array();
		foreach ( array( 'layout', 'tree', 'editor', 'help', 'preview', 'workspace' ) as $style ) {
			$handle = 'atx-vb-' . $style;
			wp_enqueue_style( $handle, $base . '/css/builder-' . $style . '.css', $style_deps, $ver );
			$style_deps = array( $handle );
		}

		// Admin image picker (reused for slider/brand/icon uploads)
		$admin_base = Atx_Nav_Menu::get_module_url() . '/assets/js/admin';
		wp_enqueue_script( 'atx-nav-admin-core', $admin_base . '/core.js', array( 'jquery' ), $ver, true );
		wp_enqueue_script( 'atx-nav-admin-picker', $admin_base . '/picker.js', array( 'atx-nav-admin-core' ), $ver, true );
		wp_localize_script( 'atx-nav-admin-core', 'atxNavAdmin', array(
			'nonce' => wp_create_nonce( 'atx_nav_admin' ),
		) );

		// Output image picker template
		$tmpl_path = Atx_Nav_Menu::get_module_path() . '/assets/js/admin/templates/image-picker.html';
		if ( file_exists( $tmpl_path ) ) {
			add_action( 'admin_footer', function() use ( $tmpl_path ) {
				echo '<script type="text/html" id="atx-tmpl-image-picker">';
				echo file_get_contents( $tmpl_path );
				echo '</script>';
			} );
		}

		$js_files = array(
			'core'    => array( 'jquery', 'jquery-ui-sortable' ),
			'tree'    => array( 'atx-vb-core' ),
			'editor'  => array( 'atx-vb-core', 'atx-nav-admin-picker' ),
			'preview' => array( 'atx-vb-core' ),
			'actions' => array( 'atx-vb-core' ),
			'extras'  => array( 'atx-vb-core', 'atx-vb-editor', 'atx-nav-admin-picker' ),
			'options' => array( 'atx-vb-core', 'atx-vb-editor', 'atx-vb-extras' ),
			'workspace' => array( 'atx-vb-core', 'atx-vb-tree', 'atx-vb-editor', 'atx-vb-preview', 'atx-vb-actions', 'atx-vb-extras', 'atx-vb-options' ),
		);

		foreach ( $js_files as $file => $deps ) {
			wp_enqueue_script( 'atx-vb-' . $file, $base . '/js/builder-' . $file . '.js', $deps, $ver, true );
		}

		// Get icons for the editor
		$icons = array();
		if ( class_exists( 'Atx_Nav_Menu_Icons' ) ) {
			$raw = Atx_Nav_Menu_Icons::get_predefined_icons();
			foreach ( $raw as $key => $icon ) {
				$icons[ $key ] = array( 'label' => $icon['label'], 'svg' => $icon['svg'] );
			}
		}

		wp_localize_script( 'atx-vb-core', 'atxVB', array(
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'nonce'        => wp_create_nonce( 'atx_vb' ),
			'previewAction'=> self::PREVIEW_ACTION,
			'previewUrls'  => self::get_frontend_preview_urls(),
			'menuUrl'      => admin_url( 'nav-menus.php' ),
			'locations'    => self::get_registered_locations(),
			'menuLocation' => $current_location,
			'extensions'   => self::get_extensions_for_location( $current_location ),
			'acfFields'    => self::get_acf_menu_item_field_defs(),
			'icons'        => $icons,
		) );
	}

	/**
	 * Render the builder page
	 */
	public function render_page() {
		echo Atx_Nav_Menu::get_template( '../visual-builder/templates/builder-page', array(
			'locations'        => self::get_registered_locations(),
			'current_location' => self::get_requested_menu_location(),
		), false );
	}

	// ── AJAX: Load menu items ──

	public function ajax_load() {
		check_ajax_referer( 'atx_vb', '_wpnonce' );
		if ( ! current_user_can( Atx_Nav_Menu_Config::get( 'capability' ) ) ) wp_send_json_error();

		$menu_location = self::get_requested_menu_location();
		$menu_id       = self::get_menu_id_for_location( $menu_location );

		if ( ! $menu_id ) {
			wp_send_json_error( 'No menu assigned to ' . $menu_location );
		}

		$menu  = wp_get_nav_menu_object( $menu_id );
		$items = self::get_builder_items( $menu_id );
		$hash  = self::get_menu_state_hash( $menu_id, $menu ? $menu->name : '', $items );

		wp_send_json_success( array(
			'items'         => $items,
			'menu_id'       => $menu_id,
			'menu_name'     => $menu ? $menu->name : '',
			'menu_location' => $menu_location,
			'extensions'    => self::get_extensions_for_location( $menu_location ),
			'base_hash'     => $hash,
			'revisions'     => self::get_revision_summaries( $menu_id ),
		) );
	}

	// ── AJAX: Save all items (positions, parents, titles, etc.) ──

	public function ajax_save() {
		check_ajax_referer( 'atx_vb', '_wpnonce' );
		if ( ! current_user_can( Atx_Nav_Menu_Config::get( 'capability' ) ) ) wp_send_json_error();

		$items = json_decode( wp_unslash( $_POST['items'] ?? '[]' ), true );
		if ( ! is_array( $items ) ) wp_send_json_error( 'Invalid menu data.' );

		$menu_location = self::get_requested_menu_location();
		$menu_id       = self::get_menu_id_for_location( $menu_location );
		if ( ! $menu_id ) wp_send_json_error( 'No menu.' );

		$required_error = self::validate_required_acf_values( $items );
		if ( $required_error ) {
			wp_send_json_error( $required_error );
		}

		$menu          = wp_get_nav_menu_object( $menu_id );
		$menu_name     = sanitize_text_field( wp_unslash( $_POST['menu_name'] ?? '' ) );
		$base_hash     = sanitize_text_field( wp_unslash( $_POST['base_hash'] ?? '' ) );
		$force         = ! empty( $_POST['force'] );
		$current_items = self::get_builder_items( $menu_id );
		$current_name  = $menu ? $menu->name : '';
		$current_hash  = self::get_menu_state_hash( $menu_id, $current_name, $current_items );

		if ( ! $force && $base_hash && ! hash_equals( $current_hash, $base_hash ) ) {
			wp_send_json_error( array(
				'code'         => 'edit_conflict',
				'message'      => __( 'This menu changed after you opened it. Reload the latest version or overwrite it with your changes.', 'atx_theme' ),
				'current_hash' => $current_hash,
			), 409 );
		}

		if ( '' === $menu_name && $menu ) {
			$menu_name = $menu->name;
		}

		if ( '' === $menu_name ) {
			wp_send_json_error( 'Menu name cannot be empty.' );
		}

		$result = self::apply_menu_state( $menu_id, $menu_location, $menu_name, $items );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		$changed = ! hash_equals( $current_hash, $result['hash'] );
		if ( $changed ) {
			self::record_revision( $menu_id, $menu_location, $current_name, $current_items, __( 'Before save', 'atx_theme' ) );
		}

		wp_send_json_success( array(
			'message'    => 'Saved.',
			'menu_name'  => $result['menu_name'],
			'items'      => $result['items'],
			'base_hash'  => $result['hash'],
			'revisions'  => self::get_revision_summaries( $menu_id ),
			'id_map'     => $result['id_map'],
		) );
	}

	private static function get_builder_items( $menu_id ) {
		$items = array();

		foreach ( (array) wp_get_nav_menu_items( $menu_id ) as $item ) {
			$items[] = array(
				'id'        => intval( $item->ID ),
				'title'     => wp_specialchars_decode( $item->title, ENT_QUOTES ),
				'url'       => $item->url,
				'parent_id' => intval( $item->menu_item_parent ),
				'position'  => intval( $item->menu_order ),
				'classes'   => array_values( array_filter( (array) $item->classes ) ),
				'type'      => $item->type,
				'object'    => $item->object,
				'object_id' => intval( $item->object_id ),
				'acf'       => self::get_acf_menu_item_values( $item->ID ),
				'icon'      => get_post_meta( $item->ID, '_atx_nav_icon', true ) ?: '',
				'extras'    => self::get_item_extras( $item->ID ),
				'is_new'    => false,
			);
		}

		return $items;
	}

	private static function get_item_extras( $item_id ) {
		$slider_items = get_post_meta( $item_id, '_atx_nav_slider_items', true );
		$slider_items = is_array( $slider_items ) ? $slider_items : array();
		foreach ( $slider_items as &$slide ) {
			$slide['image_url'] = ! empty( $slide['image'] ) ? wp_get_attachment_image_url( $slide['image'], 'thumbnail' ) : '';
		}
		unset( $slide );

		$brand_items = get_post_meta( $item_id, '_atx_nav_brand_items', true );
		$brand_items = is_array( $brand_items ) ? $brand_items : array();
		foreach ( $brand_items as &$brand ) {
			$brand['logo_url'] = ! empty( $brand['logo'] ) ? wp_get_attachment_image_url( $brand['logo'], 'thumbnail' ) : '';
		}
		unset( $brand );

		$custom_icon_id = absint( get_post_meta( $item_id, '_atx_nav_icon_custom', true ) );

		return array(
			'slider_enabled'  => get_post_meta( $item_id, '_atx_nav_slider_enabled', true ) ? '1' : '',
			'slider_items'    => $slider_items,
			'brands_enabled'  => get_post_meta( $item_id, '_atx_nav_brands_enabled', true ) ? '1' : '',
			'brand_items'     => $brand_items,
			'custom_icon_id'  => $custom_icon_id,
			'custom_icon_url' => $custom_icon_id ? ( wp_get_attachment_image_url( $custom_icon_id, 'thumbnail' ) ?: '' ) : '',
		);
	}

	private static function sanitize_item_extras( $extras ) {
		$extras = is_array( $extras ) ? $extras : array();

		$slides = array();
		foreach ( (array) ( $extras['slider_items'] ?? array() ) as $slide ) {
			$slides[] = array(
				'image'          => absint( $slide['image'] ?? 0 ),
				'link'           => esc_url_raw( $slide['link'] ?? '' ),
				'badge'          => sanitize_text_field( $slide['badge'] ?? '' ),
				'title'          => sanitize_text_field( $slide['title'] ?? '' ),
				'description'    => sanitize_text_field( $slide['description'] ?? '' ),
				'original_price' => sanitize_text_field( $slide['original_price'] ?? '' ),
				'sale_price'     => sanitize_text_field( $slide['sale_price'] ?? '' ),
			);
		}

		$brands = array();
		foreach ( (array) ( $extras['brand_items'] ?? array() ) as $brand ) {
			$brands[] = array(
				'logo' => absint( $brand['logo'] ?? 0 ),
				'name' => sanitize_text_field( $brand['name'] ?? '' ),
				'link' => esc_url_raw( $brand['link'] ?? '' ),
			);
		}

		return array(
			'slider_enabled' => ! empty( $extras['slider_enabled'] ) ? '1' : '',
			'slider_items'   => $slides,
			'brands_enabled' => ! empty( $extras['brands_enabled'] ) ? '1' : '',
			'brand_items'    => $brands,
			'custom_icon_id' => absint( $extras['custom_icon_id'] ?? 0 ),
		);
	}

	private static function save_item_extras( $item_id, $extras ) {
		$extras = self::sanitize_item_extras( $extras );

		update_post_meta( $item_id, '_atx_nav_slider_enabled', $extras['slider_enabled'] );
		update_post_meta( $item_id, '_atx_nav_slider_items', $extras['slider_items'] );
		update_post_meta( $item_id, '_atx_nav_brands_enabled', $extras['brands_enabled'] );
		update_post_meta( $item_id, '_atx_nav_brand_items', $extras['brand_items'] );
		update_post_meta( $item_id, '_atx_nav_icon_custom', $extras['custom_icon_id'] );
	}

	private static function sanitize_builder_items( $items ) {
		$clean = array();
		$seen  = array();

		foreach ( (array) $items as $index => $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$id = intval( $item['id'] ?? 0 );
			if ( ! $id || isset( $seen[ (string) $id ] ) ) {
				$id = 2000000000 + $index;
			}
			$seen[ (string) $id ] = true;

			$clean[] = array(
				'id'        => $id,
				'title'     => sanitize_text_field( $item['title'] ?? '' ),
				'url'       => esc_url_raw( $item['url'] ?? '#' ) ?: '#',
				'parent_id' => intval( $item['parent_id'] ?? 0 ),
				'position'  => max( 1, intval( $item['position'] ?? ( $index + 1 ) ) ),
				'classes'   => array_values( array_filter( array_map( 'sanitize_html_class', (array) ( $item['classes'] ?? array() ) ) ) ),
				'type'      => sanitize_key( $item['type'] ?? 'custom' ) ?: 'custom',
				'object'    => sanitize_key( $item['object'] ?? 'custom' ) ?: 'custom',
				'object_id' => absint( $item['object_id'] ?? 0 ),
				'acf'       => is_array( $item['acf'] ?? null ) ? $item['acf'] : array(),
				'icon'      => sanitize_text_field( $item['icon'] ?? '' ),
				'extras'    => self::sanitize_item_extras( $item['extras'] ?? array() ),
				'is_new'    => ! empty( $item['is_new'] ),
			);
		}

		usort( $clean, function( $a, $b ) {
			return $a['position'] <=> $b['position'];
		} );

		return $clean;
	}

	private static function get_menu_item_update_args( $item, $parent_id ) {
		$args = array(
			'menu-item-title'     => wp_slash( $item['title'] ),
			'menu-item-parent-id' => intval( $parent_id ),
			'menu-item-position'  => intval( $item['position'] ),
			'menu-item-status'    => 'publish',
			'menu-item-classes'   => implode( ' ', $item['classes'] ),
			'menu-item-type'      => $item['type'],
			'menu-item-object'    => $item['object'],
		);

		if ( in_array( $item['type'], array( 'post_type', 'taxonomy' ), true ) && $item['object_id'] ) {
			$args['menu-item-object-id'] = $item['object_id'];
		} else {
			$args['menu-item-type']   = 'custom';
			$args['menu-item-object'] = 'custom';
			$args['menu-item-url']    = $item['url'] ?: '#';
		}

		return $args;
	}

	private static function apply_menu_state( $menu_id, $menu_location, $menu_name, $items ) {
		global $wpdb;

		$items         = self::sanitize_builder_items( $items );
		$current_items = (array) wp_get_nav_menu_items( $menu_id );
		$current_ids   = array();
		foreach ( $current_items as $current_item ) {
			$current_ids[ intval( $current_item->ID ) ] = true;
		}

		$transaction_started = false !== $wpdb->query( 'START TRANSACTION' );
		$menu = wp_get_nav_menu_object( $menu_id );
		if ( ! $menu || $menu->name !== $menu_name ) {
			$updated_menu = wp_update_nav_menu_object( $menu_id, array( 'menu-name' => $menu_name ) );
			if ( is_wp_error( $updated_menu ) ) {
				self::rollback_menu_save( $transaction_started, $menu_id, array_keys( $current_ids ), array() );
				return $updated_menu;
			}
		}

		$id_map      = array();
		$created_ids = array();

		foreach ( $items as $item ) {
			$client_id = intval( $item['id'] );
			$is_new    = $item['is_new'] || ! isset( $current_ids[ $client_id ] );
			if ( ! $is_new ) {
				continue;
			}

			$args   = self::get_menu_item_update_args( $item, 0 );
			$new_id = wp_update_nav_menu_item( $menu_id, 0, $args );
			if ( is_wp_error( $new_id ) ) {
				self::rollback_menu_save( $transaction_started, $menu_id, array_keys( $current_ids ), $created_ids );
				return $new_id;
			}

			$id_map[ (string) $client_id ] = intval( $new_id );
			$created_ids[]                 = intval( $new_id );
		}

		$saved_ids = array();
		foreach ( $items as $item ) {
			$client_id = intval( $item['id'] );
			$item_id   = intval( $id_map[ (string) $client_id ] ?? $client_id );
			$parent_id = intval( $item['parent_id'] );
			$parent_id = intval( $id_map[ (string) $parent_id ] ?? $parent_id );

			$updated_id = wp_update_nav_menu_item(
				$menu_id,
				$item_id,
				self::get_menu_item_update_args( $item, $parent_id )
			);

			if ( is_wp_error( $updated_id ) ) {
				self::rollback_menu_save( $transaction_started, $menu_id, array_keys( $current_ids ), $created_ids );
				return $updated_id;
			}

			$saved_ids[ $item_id ] = true;
			update_post_meta( $item_id, '_atx_nav_icon', $item['icon'] );
			self::save_item_extras( $item_id, $item['extras'] );
			self::save_acf_menu_item_values( $item_id, $item['acf'] );
		}

		foreach ( $current_ids as $current_id => $_unused ) {
			if ( ! isset( $saved_ids[ $current_id ] ) ) {
				if ( ! wp_delete_post( $current_id, true ) ) {
					self::rollback_menu_save( $transaction_started, $menu_id, array_keys( $current_ids ), $created_ids );
					return new WP_Error( 'atx_vb_delete_failed', __( 'The menu could not be saved completely, so no changes were applied.', 'atx_theme' ) );
				}
			}
		}

		if ( $transaction_started && false === $wpdb->query( 'COMMIT' ) ) {
			self::rollback_menu_save( true, $menu_id, array_keys( $current_ids ), $created_ids );
			return new WP_Error( 'atx_vb_commit_failed', __( 'The menu save could not be committed.', 'atx_theme' ) );
		}

		$saved_items = self::get_builder_items( $menu_id );
		$hash        = self::get_menu_state_hash( $menu_id, $menu_name, $saved_items );

		set_transient( self::get_preview_transient_key( $menu_location ), $saved_items, 300 );

		return array(
			'menu_name' => $menu_name,
			'items'     => $saved_items,
			'hash'      => $hash,
			'id_map'    => $id_map,
		);
	}

	private static function rollback_menu_save( $transaction_started, $menu_id, $existing_ids, $created_ids ) {
		global $wpdb;

		if ( $transaction_started ) {
			$wpdb->query( 'ROLLBACK' );
		} else {
			foreach ( $created_ids as $created_id ) {
				wp_delete_post( $created_id, true );
			}
		}

		clean_term_cache( $menu_id, 'nav_menu' );
		foreach ( array_unique( array_merge( (array) $existing_ids, (array) $created_ids ) ) as $item_id ) {
			clean_post_cache( $item_id );
		}
	}

	private static function get_menu_state_hash( $menu_id, $menu_name = '', $items = null ) {
		if ( null === $items ) {
			$items = self::get_builder_items( $menu_id );
		}
		if ( '' === $menu_name ) {
			$menu      = wp_get_nav_menu_object( $menu_id );
			$menu_name = $menu ? $menu->name : '';
		}

		return hash( 'sha256', wp_json_encode( array(
			'menu_name' => (string) $menu_name,
			'items'     => array_values( (array) $items ),
		) ) );
	}

	private static function get_revisions_option_key( $menu_id ) {
		return 'atx_vb_revisions_' . absint( $menu_id );
	}

	private static function get_revisions( $menu_id ) {
		$revisions = get_option( self::get_revisions_option_key( $menu_id ), array() );
		return is_array( $revisions ) ? $revisions : array();
	}

	private static function get_revision_summaries( $menu_id ) {
		return array_map( function( $revision ) {
			return array(
				'id'         => $revision['id'] ?? '',
				'created_at' => $revision['created_at'] ?? '',
				'user_name'  => $revision['user_name'] ?? '',
				'note'       => $revision['note'] ?? '',
				'item_count' => count( (array) ( $revision['items'] ?? array() ) ),
			);
		}, self::get_revisions( $menu_id ) );
	}

	private static function record_revision( $menu_id, $menu_location, $menu_name, $items, $note ) {
		$revisions = self::get_revisions( $menu_id );
		$hash      = self::get_menu_state_hash( $menu_id, $menu_name, $items );

		if ( ! empty( $revisions[0]['hash'] ) && hash_equals( $revisions[0]['hash'], $hash ) ) {
			return;
		}

		$user = wp_get_current_user();
		array_unshift( $revisions, array(
			'id'            => wp_generate_uuid4(),
			'created_at'    => current_time( 'mysql' ),
			'user_id'       => get_current_user_id(),
			'user_name'     => $user->display_name ?: $user->user_login,
			'note'          => sanitize_text_field( $note ),
			'menu_location' => sanitize_key( $menu_location ),
			'menu_name'     => sanitize_text_field( $menu_name ),
			'items'         => array_values( (array) $items ),
			'hash'          => $hash,
		) );

		update_option( self::get_revisions_option_key( $menu_id ), array_slice( $revisions, 0, 10 ), false );
	}

	// ── AJAX: Add new item ──

	public function ajax_add_item() {
		check_ajax_referer( 'atx_vb', '_wpnonce' );
		if ( ! current_user_can( Atx_Nav_Menu_Config::get( 'capability' ) ) ) wp_send_json_error();

		$menu_location = self::get_requested_menu_location();
		$menu_id       = self::get_menu_id_for_location( $menu_location );
		if ( ! $menu_id ) wp_send_json_error( 'No menu.' );

		$title     = sanitize_text_field( $_POST['title'] ?? 'New Item' );
		$parent_id = intval( $_POST['parent_id'] ?? 0 );
		$type      = sanitize_key( $_POST['item_type'] ?? 'custom' );
		$object    = sanitize_key( $_POST['object'] ?? 'custom' );
		$object_id = absint( $_POST['object_id'] ?? 0 );
		$url       = esc_url_raw( $_POST['url'] ?? '#' );

		$args = array(
			'menu-item-title'     => $title,
			'menu-item-url'       => $url ?: '#',
			'menu-item-parent-id' => $parent_id,
			'menu-item-status'    => 'publish',
			'menu-item-type'      => 'custom',
		);

		if ( $type === 'post_type' && $object && $object_id ) {
			$args['menu-item-type']      = 'post_type';
			$args['menu-item-object']    = $object;
			$args['menu-item-object-id'] = $object_id;
			unset( $args['menu-item-url'] );
		} elseif ( $type === 'taxonomy' && $object && $object_id ) {
			$args['menu-item-type']      = 'taxonomy';
			$args['menu-item-object']    = $object;
			$args['menu-item-object-id'] = $object_id;
			unset( $args['menu-item-url'] );
		}

		$new_id = wp_update_nav_menu_item( $menu_id, 0, $args );

		if ( is_wp_error( $new_id ) ) {
			wp_send_json_error( $new_id->get_error_message() );
		}

		$item = wp_setup_nav_menu_item( get_post( $new_id ) );
		wp_send_json_success( array(
			'id'        => $new_id,
			'title'     => wp_specialchars_decode( $item ? $item->title : $title, ENT_QUOTES ),
			'url'       => $item ? $item->url : ( $url ?: '#' ),
			'type'      => $item ? $item->type : $type,
			'object'    => $item ? $item->object : $object,
			'object_id' => $item ? $item->object_id : $object_id,
		) );
	}

	public function ajax_search_items() {
		check_ajax_referer( 'atx_vb', '_wpnonce' );
		if ( ! current_user_can( Atx_Nav_Menu_Config::get( 'capability' ) ) ) wp_send_json_error();

		$search           = sanitize_text_field( $_POST['search'] ?? '' );
		$priority_results = array();
		$results          = array();

		$post_types = get_post_types( array( 'show_in_nav_menus' => true ), 'objects' );
		foreach ( $post_types as $post_type => $post_type_obj ) {
			$matches_post_type = self::search_matches_object_label( $search, array(
				$post_type,
				$post_type_obj->label ?? '',
				$post_type_obj->labels->name ?? '',
				$post_type_obj->labels->singular_name ?? '',
				$post_type_obj->labels->menu_name ?? '',
			) );
			$posts = get_posts( array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				's'              => $matches_post_type ? '' : $search,
				'posts_per_page' => $matches_post_type ? 40 : 8,
				'orderby'        => $matches_post_type ? 'title' : ( $search ? 'relevance' : 'date' ),
				'order'          => $matches_post_type ? 'ASC' : 'DESC',
			) );

			foreach ( $posts as $post ) {
				$result = array(
					'id'        => $post->ID,
					'title'     => wp_specialchars_decode( get_the_title( $post ), ENT_QUOTES ),
					'url'       => get_permalink( $post ),
					'type'      => 'post_type',
					'object'    => $post_type,
					'object_id' => $post->ID,
					'group'     => $post_type_obj->labels->name,
				);
				if ( $matches_post_type ) {
					$priority_results[] = $result;
				} else {
					$results[] = $result;
				}
			}
		}

		$taxonomies = get_taxonomies( array( 'show_in_nav_menus' => true ), 'objects' );
		foreach ( $taxonomies as $taxonomy => $taxonomy_obj ) {
			$matches_taxonomy = self::search_matches_object_label( $search, array(
				$taxonomy,
				$taxonomy_obj->label ?? '',
				$taxonomy_obj->labels->name ?? '',
				$taxonomy_obj->labels->singular_name ?? '',
				$taxonomy_obj->labels->menu_name ?? '',
			) );
			$terms = get_terms( array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'search'     => $matches_taxonomy ? '' : $search,
				'number'     => $matches_taxonomy ? 40 : 8,
				'orderby'    => 'name',
				'order'      => 'ASC',
			) );

			if ( is_wp_error( $terms ) ) {
				continue;
			}

			foreach ( $terms as $term ) {
				$term_link = get_term_link( $term );
				if ( is_wp_error( $term_link ) ) {
					continue;
				}

				$result = array(
					'id'        => $term->term_id,
					'title'     => wp_specialchars_decode( $term->name, ENT_QUOTES ),
					'url'       => $term_link,
					'type'      => 'taxonomy',
					'object'    => $taxonomy,
					'object_id' => $term->term_id,
					'group'     => $taxonomy_obj->labels->name,
				);
				if ( $matches_taxonomy ) {
					$priority_results[] = $result;
				} else {
					$results[] = $result;
				}
			}
		}

		$merged = array();
		$seen   = array();
		foreach ( array_merge( $priority_results, $results ) as $result ) {
			$key = $result['type'] . ':' . $result['object'] . ':' . $result['object_id'];
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$merged[]     = $result;
			if ( count( $merged ) >= 40 ) {
				break;
			}
		}

		wp_send_json_success( array( 'items' => $merged ) );
	}

	private static function search_matches_object_label( $search, $labels ) {
		$normalize = static function( $value ) {
			$value = strtolower( remove_accents( (string) $value ) );
			$value = preg_replace( '/[\\s_-]+/', ' ', $value );
			return trim( $value );
		};

		$needle = $normalize( $search );
		if ( '' === $needle ) {
			return false;
		}

		foreach ( array_filter( $labels ) as $label ) {
			$haystack = $normalize( $label );
			if ( '' !== $haystack && false !== strpos( $haystack, $needle ) ) {
				return true;
			}
		}

		return false;
	}

	public function ajax_health_check() {
		check_ajax_referer( 'atx_vb', '_wpnonce' );
		if ( ! current_user_can( Atx_Nav_Menu_Config::get( 'capability' ) ) ) wp_send_json_error();

		$items = json_decode( wp_unslash( $_POST['items'] ?? '[]' ), true );
		if ( ! is_array( $items ) ) {
			wp_send_json_error( 'Invalid menu data.' );
		}

		$items    = self::sanitize_builder_items( $items );
		$lookup   = array();
		$warnings = array();
		$urls     = array();

		foreach ( $items as $item ) {
			$lookup[ intval( $item['id'] ) ] = $item;
		}

		foreach ( $items as $item ) {
			$item_id = intval( $item['id'] );
			$title   = trim( (string) $item['title'] );
			$url     = trim( (string) $item['url'] );

			if ( '' === $title ) {
				$warnings[] = self::make_health_warning( 'error', 'empty_title', $item, __( 'This item has no navigation label.', 'atx_theme' ) );
			} elseif ( in_array( strtolower( $title ), array( 'click here', 'learn more', 'read more', 'link' ), true ) ) {
				$warnings[] = self::make_health_warning( 'warning', 'weak_label', $item, __( 'Use a more descriptive navigation label.', 'atx_theme' ) );
			}

			if ( '' === $url || '#' === $url ) {
				$warnings[] = self::make_health_warning( 'warning', 'placeholder_url', $item, __( 'This item uses a placeholder link (#).', 'atx_theme' ) );
			}

			$depth   = 0;
			$parent  = intval( $item['parent_id'] );
			$visited = array( $item_id => true );
			while ( $parent && isset( $lookup[ $parent ] ) && ! isset( $visited[ $parent ] ) ) {
				$visited[ $parent ] = true;
				$depth++;
				$parent = intval( $lookup[ $parent ]['parent_id'] );
			}
			if ( $depth > 3 ) {
				$warnings[] = self::make_health_warning(
					'warning',
					'deep_nesting',
					$item,
					sprintf( __( 'This item is nested %d levels deep; consider simplifying the menu.', 'atx_theme' ), $depth )
				);
			}

			if ( 'post_type' === $item['type'] ) {
				$post = get_post( $item['object_id'] );
				if ( ! $post ) {
					$warnings[] = self::make_health_warning( 'error', 'missing_content', $item, __( 'The linked content no longer exists.', 'atx_theme' ) );
				} elseif ( 'publish' !== $post->post_status ) {
					$warnings[] = self::make_health_warning(
						'warning',
						'unpublished_content',
						$item,
						sprintf( __( 'The linked content is currently %s.', 'atx_theme' ), sanitize_text_field( $post->post_status ) )
					);
				}
			} elseif ( 'taxonomy' === $item['type'] ) {
				$term = get_term( $item['object_id'], $item['object'] );
				if ( ! $term || is_wp_error( $term ) ) {
					$warnings[] = self::make_health_warning( 'error', 'missing_term', $item, __( 'The linked term no longer exists.', 'atx_theme' ) );
				}
			}

			if ( preg_match( '#^https?://#i', $url ) ) {
				$normalized_url = untrailingslashit( strtolower( strtok( $url, '#' ) ) );
				if ( $normalized_url ) {
					$urls[ $normalized_url ][] = $item;
				}
			}
		}

		foreach ( $urls as $duplicate_items ) {
			if ( count( $duplicate_items ) < 2 ) {
				continue;
			}
			foreach ( $duplicate_items as $duplicate_item ) {
				$warnings[] = self::make_health_warning( 'warning', 'duplicate_url', $duplicate_item, __( 'Another menu item uses the same destination.', 'atx_theme' ) );
			}
		}

		$site_host     = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		$external_urls = array();
		foreach ( $urls as $normalized_url => $url_items ) {
			$url_host = strtolower( (string) wp_parse_url( $normalized_url, PHP_URL_HOST ) );
			if ( $url_host && $url_host !== $site_host ) {
				$external_urls[ $normalized_url ] = $url_items;
			}
		}

		$checked = 0;
		foreach ( $external_urls as $external_url => $url_items ) {
			if ( $checked >= 8 ) {
				break;
			}
			$checked++;

			$response = wp_safe_remote_head( $external_url, array(
				'timeout'     => 3,
				'redirection' => 3,
				'user-agent'  => 'ATX Visual Nav Builder/' . Atx_Nav_Menu::VERSION,
			) );
			$status = is_wp_error( $response ) ? 0 : intval( wp_remote_retrieve_response_code( $response ) );
			if ( is_wp_error( $response ) || $status >= 400 ) {
				foreach ( $url_items as $url_item ) {
					$message    = is_wp_error( $response )
						? __( 'The external link could not be reached.', 'atx_theme' )
						: sprintf( __( 'The external link returned HTTP %d.', 'atx_theme' ), $status );
					$warnings[] = self::make_health_warning( 'warning', 'external_link', $url_item, $message );
				}
			}
		}

		$priority = array( 'error' => 0, 'warning' => 1, 'info' => 2 );
		usort( $warnings, function( $a, $b ) use ( $priority ) {
			return ( $priority[ $a['severity'] ] ?? 9 ) <=> ( $priority[ $b['severity'] ] ?? 9 );
		} );

		wp_send_json_success( array(
			'warnings'         => $warnings,
			'item_count'       => count( $items ),
			'external_checked' => $checked,
			'external_skipped' => max( 0, count( $external_urls ) - $checked ),
		) );
	}

	private static function make_health_warning( $severity, $code, $item, $message ) {
		return array(
			'severity' => sanitize_key( $severity ),
			'code'     => sanitize_key( $code ),
			'item_id'  => intval( $item['id'] ?? 0 ),
			'title'    => sanitize_text_field( $item['title'] ?? __( 'Untitled item', 'atx_theme' ) ),
			'message'  => sanitize_text_field( $message ),
		);
	}

	public function ajax_revisions() {
		check_ajax_referer( 'atx_vb', '_wpnonce' );
		if ( ! current_user_can( Atx_Nav_Menu_Config::get( 'capability' ) ) ) wp_send_json_error();

		$menu_location = self::get_requested_menu_location();
		$menu_id       = self::get_menu_id_for_location( $menu_location );
		$revision_id   = sanitize_text_field( wp_unslash( $_POST['revision_id'] ?? '' ) );
		$base_hash     = sanitize_text_field( wp_unslash( $_POST['base_hash'] ?? '' ) );
		$force         = ! empty( $_POST['force'] );

		if ( ! $menu_id || ! $revision_id ) {
			wp_send_json_error( 'Missing revision.' );
		}

		$revision = null;
		foreach ( self::get_revisions( $menu_id ) as $candidate ) {
			if ( ( $candidate['id'] ?? '' ) === $revision_id ) {
				$revision = $candidate;
				break;
			}
		}

		if ( ! $revision ) {
			wp_send_json_error( 'Revision not found.' );
		}

		$menu          = wp_get_nav_menu_object( $menu_id );
		$current_name  = $menu ? $menu->name : '';
		$current_items = self::get_builder_items( $menu_id );
		$current_hash  = self::get_menu_state_hash( $menu_id, $current_name, $current_items );
		if ( ! $force && $base_hash && ! hash_equals( $current_hash, $base_hash ) ) {
			wp_send_json_error( array(
				'code'         => 'edit_conflict',
				'message'      => __( 'This menu changed before the revision could be restored.', 'atx_theme' ),
				'current_hash' => $current_hash,
			), 409 );
		}

		$result = self::apply_menu_state(
			$menu_id,
			$menu_location,
			sanitize_text_field( $revision['menu_name'] ?? $current_name ),
			(array) ( $revision['items'] ?? array() )
		);
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		self::record_revision( $menu_id, $menu_location, $current_name, $current_items, __( 'Before revision restore', 'atx_theme' ) );

		wp_send_json_success( array(
			'message'   => __( 'Revision restored.', 'atx_theme' ),
			'menu_name' => $result['menu_name'],
			'items'     => $result['items'],
			'base_hash' => $result['hash'],
			'revisions' => self::get_revision_summaries( $menu_id ),
		) );
	}

	public function ajax_copy_items() {
		check_ajax_referer( 'atx_vb', '_wpnonce' );
		if ( ! current_user_can( Atx_Nav_Menu_Config::get( 'capability' ) ) ) wp_send_json_error();

		$source_location = self::get_requested_menu_location();
		$target_location = sanitize_key( wp_unslash( $_POST['target_location'] ?? '' ) );
		$registered      = self::get_registered_locations();
		$copied_items    = json_decode( wp_unslash( $_POST['items'] ?? '[]' ), true );

		if ( ! $target_location || ! isset( $registered[ $target_location ] ) ) {
			wp_send_json_error( 'Choose a valid target menu location.' );
		}
		if ( $target_location === $source_location ) {
			wp_send_json_error( 'Choose a different menu location.' );
		}
		if ( empty( $copied_items ) || ! is_array( $copied_items ) ) {
			wp_send_json_error( 'Choose at least one menu item to copy.' );
		}

		$target_menu_id = self::get_menu_id_for_location( $target_location );
		$target_menu    = wp_get_nav_menu_object( $target_menu_id );
		if ( ! $target_menu ) {
			wp_send_json_error( 'Could not load the target menu.' );
		}

		$target_items = self::get_builder_items( $target_menu_id );
		$position     = count( $target_items );
		foreach ( $copied_items as &$copied_item ) {
			$copied_item['is_new']  = true;
			$copied_item['position'] = ++$position;
		}
		unset( $copied_item );

		$result = self::apply_menu_state(
			$target_menu_id,
			$target_location,
			$target_menu->name,
			array_merge( $target_items, $copied_items )
		);
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		self::record_revision(
			$target_menu_id,
			$target_location,
			$target_menu->name,
			$target_items,
			sprintf( __( 'Before copying items from %s', 'atx_theme' ), $source_location )
		);

		wp_send_json_success( array(
			'message'         => sprintf( __( 'Copied %d menu items.', 'atx_theme' ), count( $copied_items ) ),
			'target_location' => $target_location,
		) );
	}

	// ── AJAX: Delete item ──

	public function ajax_delete_item() {
		check_ajax_referer( 'atx_vb', '_wpnonce' );
		if ( ! current_user_can( Atx_Nav_Menu_Config::get( 'capability' ) ) ) wp_send_json_error();

		$item_id = intval( $_POST['item_id'] ?? 0 );
		if ( ! $item_id ) wp_send_json_error( 'No item ID.' );

		wp_delete_post( $item_id, true );
		wp_send_json_success();
	}

	// ── AJAX: Render just the nav HTML for hot-swap ──

	public function ajax_render_nav() {
		check_ajax_referer( 'atx_vb', '_wpnonce' );
		if ( ! current_user_can( Atx_Nav_Menu_Config::get( 'capability' ) ) ) wp_send_json_error();

		$menu_location = self::get_requested_menu_location();
		$html          = self::render_preview_nav_html( $menu_location );

		wp_send_json_success( array( 'html' => $html ) );
	}

	// ── AJAX: Sync the staged workspace into the private preview ──

	public function ajax_preview_sync() {
		check_ajax_referer( 'atx_vb', '_wpnonce' );
		if ( ! current_user_can( Atx_Nav_Menu_Config::get( 'capability' ) ) ) wp_send_json_error();

		// Save items to transient for live preview
		$items = json_decode( wp_unslash( $_POST['items'] ?? '[]' ), true );
		if ( is_array( $items ) ) {
			set_transient( self::get_preview_transient_key( self::get_requested_menu_location() ), $items, 300 );
		}

		wp_send_json_success();
	}

	/**
	 * Keep the real front-end preview private, fresh, and isolated to the
	 * authenticated builder session.
	 */
	public function prepare_frontend_preview() {
		if ( ! self::is_frontend_preview_request() ) {
			return;
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		nocache_headers();
		$this->disable_complianz_in_frontend_preview();
	}

	/**
	 * Complianz wires its banner before the theme loads. Remove only its
	 * front-end output callbacks for this authenticated preview request.
	 */
	private function disable_complianz_in_frontend_preview() {
		if ( ! class_exists( 'cmplz_banner_loader' ) || ! is_callable( array( 'cmplz_banner_loader', 'this' ) ) ) {
			return;
		}

		$loader = cmplz_banner_loader::this();
		if ( ! $loader ) {
			return;
		}

		remove_action( 'wp_enqueue_scripts', array( $loader, 'enqueue_assets' ), PHP_INT_MAX - 50 );
		remove_action( 'wp_head', array( $loader, 'cookiebanner_css' ) );
		remove_action( 'wp_head', array( $loader, 'inline_cookie_script' ), 0 );
		remove_action( 'wp_footer', array( $loader, 'cookiebanner_html' ) );
		remove_action( 'wp_footer', array( $loader, 'detect_conflicts' ), PHP_INT_MAX );
		remove_action( 'wp_print_footer_scripts', array( $loader, 'inline_cookie_script' ), PHP_INT_MAX - 50 );
		remove_action( 'wp_print_footer_scripts', array( $loader, 'inline_cookie_script_no_warning' ), 10 );

		add_filter( 'cmplz_banner_html', '__return_empty_string', PHP_INT_MAX );
		add_filter( 'cmplz_manage_consent_html', '__return_empty_string', PHP_INT_MAX );
	}

	/**
	 * Hide common consent and marketing overlays before their JavaScript can
	 * paint them. These rules exist only inside the builder preview iframe.
	 */
	public function output_frontend_preview_suppression_styles() {
		if ( ! self::is_frontend_preview_request() ) {
			return;
		}
		?>
		<style id="atx-vb-preview-suppression-style">
			#cmplz-cookiebanner-container,
			#cmplz-manage-consent,
			.cmplz-cookiebanner,
			.cmplz-manage-consent,
			.cky-consent-container,
			.cky-modal,
			#onetrust-consent-sdk,
			#onetrust-banner-sdk,
			#CybotCookiebotDialog,
			#CookiebotWidget,
			#iubenda-cs-banner,
			.iubenda-cs-container,
			.qc-cmp2-container,
			.moove-gdpr-info-bar-container,
			#moove_gdpr_cookie_info_bar,
			.borlabs-cookie,
			#BorlabsCookieBox,
			.pum-overlay,
			.pum-container,
			.elementor-popup-modal,
			.sg-popup-overlay,
			.spu-bg,
			.hustle-ui,
			.brave_popup,
			.ays-pb-modals,
			body > [role="dialog"],
			body > [aria-modal="true"] {
				display: none !important;
				opacity: 0 !important;
				visibility: hidden !important;
				pointer-events: none !important;
			}
		</style>
		<?php
	}

	/**
	 * Replace only the menu assigned to the selected location. This filter is
	 * also used by themes that fetch menu items directly instead of calling
	 * wp_nav_menu(), so their original templates and styling remain intact.
	 */
	public function filter_frontend_preview_items( $items, $menu, $args ) {
		if ( ! self::is_frontend_preview_request() ) {
			return $items;
		}

		$menu_location = self::get_requested_menu_location();
		$assigned      = get_nav_menu_locations();
		$selected_id   = absint( $assigned[ $menu_location ] ?? 0 );
		$current_id    = absint( $menu->term_id ?? 0 );

		if ( ! $selected_id || $selected_id !== $current_id ) {
			return $items;
		}

		$preview_items = get_transient( self::get_preview_transient_key( $menu_location ) );
		if ( false === $preview_items || ! is_array( $preview_items ) ) {
			return $items;
		}

		return self::make_preview_items( $preview_items );
	}

	/**
	 * Make standard wp_nav_menu() output discoverable without adding a wrapper
	 * that could alter a theme's layout or CSS selectors.
	 */
	public function mark_frontend_preview_menu( $nav_menu, $args ) {
		if ( ! self::is_frontend_preview_request() || ! is_string( $nav_menu ) || '' === $nav_menu ) {
			return $nav_menu;
		}

		$menu_location = self::get_requested_menu_location();
		$is_selected   = ! empty( $args->theme_location ) && $args->theme_location === $menu_location;

		if ( ! $is_selected && ! empty( $args->menu ) ) {
			$menu          = wp_get_nav_menu_object( $args->menu );
			$assigned      = get_nav_menu_locations();
			$selected_id   = absint( $assigned[ $menu_location ] ?? 0 );
			$is_selected   = $menu && $selected_id === absint( $menu->term_id );
		}

		if ( ! $is_selected || str_contains( $nav_menu, 'data-atx-vb-menu-location=' ) ) {
			return $nav_menu;
		}

		$attribute = ' data-atx-vb-menu-location="' . esc_attr( $menu_location ) . '"';
		return preg_replace( '/<([a-z][a-z0-9:-]*)(\s|>)/i', '<$1' . $attribute . '$2', $nav_menu, 1 ) ?: $nav_menu;
	}

	/**
	 * Let unsaved ACF menu-item values appear in templates that use get_field()
	 * while rendering their navigation.
	 */
	public function filter_frontend_preview_acf_value( $value, $post_id, $field ) {
		if ( ! self::is_frontend_preview_request() ) {
			return $value;
		}

		$item_id    = absint( $post_id );
		$field_name = sanitize_key( $field['name'] ?? '' );
		if ( ! $item_id || ! $field_name ) {
			return $value;
		}

		$items = get_transient( self::get_preview_transient_key( self::get_requested_menu_location() ) );
		if ( ! is_array( $items ) ) {
			return $value;
		}

		foreach ( $items as $item ) {
			if ( absint( $item['id'] ?? 0 ) !== $item_id ) {
				continue;
			}

			$acf = is_array( $item['acf'] ?? null ) ? $item['acf'] : array();
			return array_key_exists( $field_name, $acf ) ? $acf[ $field_name ] : $value;
		}

		return $value;
	}

	/**
	 * Keep icon, slider, and brand changes inside the preview transient until
	 * the user performs the single atomic menu save.
	 */
	public function filter_frontend_preview_meta( $value, $object_id, $meta_key, $single, $meta_type = 'post' ) {
		if ( 'post' !== $meta_type || ! self::is_frontend_preview_request() || ! $meta_key ) {
			return $value;
		}

		$items = get_transient( self::get_preview_transient_key( self::get_requested_menu_location() ) );
		if ( ! is_array( $items ) ) {
			return $value;
		}

		$meta_map = array(
			'_atx_nav_icon'            => 'icon',
			'_atx_nav_slider_enabled'  => 'slider_enabled',
			'_atx_nav_slider_items'    => 'slider_items',
			'_atx_nav_brands_enabled'  => 'brands_enabled',
			'_atx_nav_brand_items'     => 'brand_items',
			'_atx_nav_icon_custom'     => 'custom_icon_id',
		);

		if ( ! isset( $meta_map[ $meta_key ] ) ) {
			return $value;
		}

		foreach ( $items as $item ) {
			if ( intval( $item['id'] ?? 0 ) !== intval( $object_id ) ) {
				continue;
			}

			if ( '_atx_nav_icon' === $meta_key ) {
				$preview_value = sanitize_text_field( $item['icon'] ?? '' );
			} else {
				$extras        = is_array( $item['extras'] ?? null ) ? $item['extras'] : array();
				$preview_value = $extras[ $meta_map[ $meta_key ] ] ?? '';
			}

			return $single ? $preview_value : array( $preview_value );
		}

		return $value;
	}

	/**
	 * Add the small bridge that finds the selected location in the real page,
	 * scrolls it into view, annotates item links for the editor, and reports
	 * whether the active template actually renders that location.
	 */
	public function output_frontend_preview_bridge() {
		if ( ! self::is_frontend_preview_request() ) {
			return;
		}

		$menu_location = self::get_requested_menu_location();
		$assigned      = get_nav_menu_locations();
		$menu_id       = absint( $assigned[ $menu_location ] ?? 0 );
		$menu          = $menu_id ? wp_get_nav_menu_object( $menu_id ) : null;
		$preview_items = get_transient( self::get_preview_transient_key( $menu_location ) );
		$preview_items = is_array( $preview_items ) ? $preview_items : array();
		$item_map      = array();

		foreach ( $preview_items as $item ) {
			$item_map[] = array(
				'id'    => absint( $item['id'] ?? 0 ),
				'title' => sanitize_text_field( $item['title'] ?? '' ),
				'url'   => esc_url_raw( $item['url'] ?? '#' ) ?: '#',
			);
		}

		$config = array(
			'location' => $menu_location,
			'menuId'   => $menu_id,
			'menuName' => $menu ? $menu->name : '',
			'menuSlug' => $menu ? $menu->slug : '',
			'items'    => $item_map,
		);
		?>
		<style id="atx-vb-frontend-preview-style">
			html { margin-top: 0 !important; scroll-behavior: auto !important; }
			#wpadminbar { display: none !important; }
			[data-atx-vb-menu-location="<?php echo esc_attr( $menu_location ); ?>"] {
				outline: 3px solid #2271b1 !important;
				outline-offset: 4px !important;
				scroll-margin: 72px !important;
			}
			[data-atx-vb-force-menu-open] {
				display: block !important;
				visibility: visible !important;
				opacity: 1 !important;
				pointer-events: auto !important;
				transform: none !important;
				translate: none !important;
				clip-path: none !important;
				height: auto !important;
				max-height: none !important;
				max-width: none !important;
				animation: none !important;
				transition: none !important;
			}
			[data-atx-vb-force-menu-open][class*="flex"] {
				display: flex !important;
			}
			[data-atx-vb-force-menu-open] * {
				animation: none !important;
				transition: none !important;
			}
			[data-atx-vb-force-menu-open] :is(
				div, nav, ul, ol, a, button, span, p, li, small, strong, em,
				h1, h2, h3, h4, h5, h6
			) {
				opacity: 1 !important;
				visibility: visible !important;
				transform: none !important;
				translate: none !important;
				clip-path: none !important;
				filter: none !important;
			}
			[data-atx-vb-force-menu-open] :is(
				[id*="backdrop"], [class*="backdrop"],
				[id*="overlay"], [class*="overlay"],
				[id*="background"], [class*="background"],
				[id*="circle"], [class*="circle"]
			) {
				position: absolute !important;
				inset: 0 !important;
				width: auto !important;
				height: auto !important;
				border-radius: 0 !important;
				opacity: 1 !important;
				transform: none !important;
			}
		</style>
		<script id="atx-vb-frontend-preview-bridge">
			(function () {
				'use strict';

				const config = <?php echo wp_json_encode( $config ); ?>;
				const roots = [];
				const overlaySelectors = [
					'#cmplz-cookiebanner-container',
					'#cmplz-manage-consent',
					'.cmplz-cookiebanner',
					'.cmplz-manage-consent',
					'.cky-consent-container',
					'.cky-modal',
					'#onetrust-consent-sdk',
					'#onetrust-banner-sdk',
					'#CybotCookiebotDialog',
					'#CookiebotWidget',
					'#iubenda-cs-banner',
					'.iubenda-cs-container',
					'.qc-cmp2-container',
					'.moove-gdpr-info-bar-container',
					'#moove_gdpr_cookie_info_bar',
					'.borlabs-cookie',
					'#BorlabsCookieBox',
					'.pum-overlay',
					'.pum-container',
					'.elementor-popup-modal',
					'.sg-popup-overlay',
					'.spu-bg',
					'.hustle-ui',
					'.brave_popup',
					'.ays-pb-modals',
					'body > [role="dialog"]',
					'body > [aria-modal="true"]'
				].join(',');

				function hidePreviewOverlay(element) {
					if (!element || element.closest('[data-atx-vb-menu-location], #atx-nav-menu')) {
						return false;
					}

					if (element.getAttribute('data-atx-vb-preview-suppressed') === 'true') {
						return false;
					}

					element.setAttribute('data-atx-vb-preview-suppressed', 'true');
					element.setAttribute('aria-hidden', 'true');
					element.style.setProperty('display', 'none', 'important');
					element.style.setProperty('visibility', 'hidden', 'important');
					element.style.setProperty('pointer-events', 'none', 'important');
					return true;
				}

				function suppressPreviewOverlays() {
					let suppressed = false;

					document.querySelectorAll(overlaySelectors).forEach(function (element) {
						suppressed = hidePreviewOverlay(element) || suppressed;
					});

					document.querySelectorAll('body *').forEach(function (element) {
						const identity = [
							element.id || '',
							typeof element.className === 'string' ? element.className : ''
						].join(' ').toLowerCase();

						if (!/(cookie|consent|gdpr|popup|newsletter|subscribe|interstitial|modal-backdrop|pum-overlay)/.test(identity)) {
							return;
						}

						const style = window.getComputedStyle(element);
						if (style.position === 'fixed' || style.position === 'sticky' || element.getAttribute('role') === 'dialog') {
							suppressed = hidePreviewOverlay(element) || suppressed;
						}
					});

					if (suppressed) {
						document.body.classList.remove(
							'cmplz-banner-active',
							'cky-modal-open',
							'pum-open',
							'modal-open',
							'no-scroll',
							'overflow-hidden'
						);
						document.documentElement.style.setProperty('overflow', 'auto', 'important');
						document.body.style.setProperty('overflow', 'auto', 'important');
						document.body.style.removeProperty('position');
					}
				}

				let suppressionFrame = 0;
				const overlayObserver = new MutationObserver(function () {
					if (suppressionFrame) return;
					suppressionFrame = window.requestAnimationFrame(function () {
						suppressionFrame = 0;
						suppressPreviewOverlays();
					});
				});

				suppressPreviewOverlays();
				overlayObserver.observe(document.body, { childList: true, subtree: true });
				window.addEventListener('load', suppressPreviewOverlays);

				function addRoot(element) {
					if (element && !roots.includes(element)) {
						roots.push(element);
					}
				}

				document.querySelectorAll('[data-atx-vb-menu-location]').forEach(function (element) {
					if (element.getAttribute('data-atx-vb-menu-location') === config.location) {
						addRoot(element);
					}
				});

				if (config.menuName) {
					document.querySelectorAll('[aria-label]').forEach(function (element) {
						if (element.getAttribute('aria-label') === config.menuName) {
							addRoot(element);
						}
					});
				}

				if (config.menuSlug) {
					addRoot(document.getElementById('menu-' + config.menuSlug));
					document.querySelectorAll('.menu-' + config.menuSlug + '-container').forEach(addRoot);
				}

				if (!roots.length && config.items.length) {
					document.querySelectorAll('.menu-item-' + config.items[0].id).forEach(function (element) {
						addRoot(element.closest('nav, [role="navigation"], header, footer, ul') || element.parentElement);
					});
				}

				function normalizeUrl(value) {
					try {
						const url = new URL(value || '#', window.location.href);
						url.hash = '';
						return url.href.replace(/\/$/, '');
					} catch (error) {
						return value || '';
					}
				}

				function annotateItems(root) {
					const used = new Set();
					root.querySelectorAll('a[href]').forEach(function (anchor) {
						const href = normalizeUrl(anchor.getAttribute('href'));
						const title = (anchor.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
						let matches = config.items.filter(function (item) {
							return !used.has(item.id) && normalizeUrl(item.url) === href;
						});

						if (matches.length > 1 || !matches.length) {
							const titleMatches = config.items.filter(function (item) {
								return !used.has(item.id) && title.includes((item.title || '').trim().toLowerCase());
							});
							if (titleMatches.length) {
								matches = titleMatches;
							}
						}

						if (!matches.length) {
							return;
						}

						const item = matches[0];
						used.add(item.id);
						anchor.dataset.itemId = String(item.id);
						const listItem = anchor.closest('li');
						if (listItem && root.contains(listItem)) {
							listItem.dataset.itemId = String(item.id);
						}
					});
				}

				roots.forEach(function (root) {
					root.setAttribute('data-atx-vb-menu-location', config.location);
					annotateItems(root);
				});

				function isVisible(element) {
					if (!element || !element.getClientRects().length) {
						return false;
					}
					const style = window.getComputedStyle(element);
					return style.display !== 'none' && style.visibility !== 'hidden';
				}

				let forcedMenuElements = [];

				function clearForcedMenuElements() {
					forcedMenuElements.forEach(function (element) {
						element.removeAttribute('data-atx-vb-force-menu-open');
					});
					forcedMenuElements = [];
				}

				function hasMobileMenuIdentity(element) {
					const identity = [
						element.id || '',
						typeof element.className === 'string' ? element.className : '',
						element.getAttribute('role') || ''
					].join(' ').toLowerCase();

					return /(mobile|offcanvas|off-canvas|drawer|burger|hamburger|menu-overlay|menu-panel)/.test(identity);
				}

				function findMobileMenuRoot() {
					return roots.find(function (root) {
						let element = root;
						while (element && element !== document.body) {
							if (hasMobileMenuIdentity(element)) {
								return true;
							}
							element = element.parentElement;
						}
						return false;
					});
				}

				function findFixedMenuRoot() {
					return roots.find(function (root) {
						let element = root.parentElement;
						while (element && element !== document.body) {
							if (window.getComputedStyle(element).position === 'fixed') {
								return true;
							}
							element = element.parentElement;
						}
						return false;
					});
				}

				function forceOpenSelectedMenu() {
					clearForcedMenuElements();

					let root = findMobileMenuRoot();

					if (root) {
						const anotherRootIsVisible = roots.some(function (candidate) {
							return candidate !== root && isVisible(candidate);
						});
						if (anotherRootIsVisible) {
							return;
						}
					} else {
						if (roots.some(isVisible)) {
							return;
						}
						root = findFixedMenuRoot();
					}

					if (!root) {
						return;
					}

					let element = root;
					while (element && element !== document.body) {
						const style = window.getComputedStyle(element);
						const shouldOpen = element === root
							|| hasMobileMenuIdentity(element)
							|| element.hidden
							|| element.getAttribute('aria-hidden') === 'true'
							|| style.display === 'none'
							|| style.visibility === 'hidden'
							|| parseFloat(style.opacity || '1') === 0
							|| style.pointerEvents === 'none'
							|| style.transform !== 'none'
							|| style.clipPath !== 'none'
							|| style.height === '0px'
							|| style.maxHeight === '0px';

						if (shouldOpen) {
							element.setAttribute('data-atx-vb-force-menu-open', 'true');
							forcedMenuElements.push(element);
						}

						element = element.parentElement;
					}
				}

				let focusRequest = 0;

				function getScrollElement() {
					return document.scrollingElement || document.documentElement;
				}

				function getMaximumScrollTop() {
					const html = document.documentElement;
					const body = document.body;
					const scrollHeight = Math.max(
						html ? html.scrollHeight : 0,
						html ? html.offsetHeight : 0,
						body ? body.scrollHeight : 0,
						body ? body.offsetHeight : 0
					);

					return Math.max(0, scrollHeight - window.innerHeight);
				}

				function isFooterLocation(target) {
					return /(^|[-_])footer($|[-_])/i.test(config.location || '')
						|| Boolean(target.closest('footer, [role="contentinfo"]'));
				}

				function getTargetScrollTop(target) {
					const maximum = getMaximumScrollTop();

					if (isFooterLocation(target)) {
						return maximum;
					}

					const scrollElement = getScrollElement();
					const current = window.scrollY
						|| window.pageYOffset
						|| (scrollElement ? scrollElement.scrollTop : 0)
						|| 0;
					const bounds = target.getBoundingClientRect();
					const centered = current + bounds.top - Math.max(0, (window.innerHeight - bounds.height) / 2);

					return Math.min(maximum, Math.max(0, centered));
				}

				function forceLocationScroll(target) {
					if (!target || !target.isConnected) {
						return;
					}

					const lenis = window.lenis;
					if (lenis && typeof lenis.resize === 'function') {
						lenis.resize();
					}

					const scrollTop = getTargetScrollTop(target);
					const scrollElement = getScrollElement();

					if (lenis && typeof lenis.scrollTo === 'function') {
						if (typeof lenis.start === 'function') {
							lenis.start();
						}
						lenis.scrollTo(scrollTop, { immediate: true, force: true });
					}

					window.scrollTo({ top: scrollTop, left: 0, behavior: 'auto' });
					if (scrollElement) {
						scrollElement.scrollTop = scrollTop;
					}

					// Keep smooth-scroll libraries aligned with the native scroll position.
					if (lenis && typeof lenis.scrollTo === 'function') {
						lenis.scrollTo(scrollTop, { immediate: true, force: true });
					}
				}

				function focusLocation() {
					const target = roots.find(isVisible) || roots[0];
					if (!target) {
						return false;
					}

					const request = ++focusRequest;
					forceLocationScroll(target);

					// Sticky sections, lazy media, and smooth-scroll libraries can change the
					// document limit after first paint, so correct the focus a few times.
					[100, 300, 700, 1200, 2000].forEach(function (delay) {
						window.setTimeout(function () {
							if (request === focusRequest) {
								forceLocationScroll(target);
							}
						}, delay);
					});

					return true;
				}

				window.AtxVBPreview = { focusLocation: focusLocation };
				forceOpenSelectedMenu();
				const found = focusLocation();
				window.addEventListener('resize', function () {
					forceOpenSelectedMenu();
					focusLocation();
				});

				if (window.parent !== window) {
					window.parent.postMessage({
						type: 'atx-vb-preview-location',
						location: config.location,
						found: found,
						menuName: config.menuName
					}, window.location.origin);
				}
			}());
		</script>
		<?php
	}

	// ── AJAX: Get extras (slider, brands, custom icon) for an item ──

	public function ajax_get_extras() {
		check_ajax_referer( 'atx_vb', '_wpnonce' );
		if ( ! current_user_can( Atx_Nav_Menu_Config::get( 'capability' ) ) ) wp_send_json_error();

		$item_id = intval( $_POST['item_id'] ?? 0 );
		if ( ! $item_id ) wp_send_json_error();

		$slider_items = get_post_meta( $item_id, '_atx_nav_slider_items', true );
		$slider_items = is_array( $slider_items ) ? $slider_items : array();

		// Add image URLs to slider items
		foreach ( $slider_items as &$slide ) {
			$slide['image_url'] = ! empty( $slide['image'] ) ? wp_get_attachment_image_url( $slide['image'], 'thumbnail' ) : '';
		}

		$brand_items = get_post_meta( $item_id, '_atx_nav_brand_items', true );
		$brand_items = is_array( $brand_items ) ? $brand_items : array();

		// Add logo URLs to brand items
		foreach ( $brand_items as &$brand ) {
			$brand['logo_url'] = ! empty( $brand['logo'] ) ? wp_get_attachment_image_url( $brand['logo'], 'thumbnail' ) : '';
		}

		$custom_icon_id  = get_post_meta( $item_id, '_atx_nav_icon_custom', true );
		$custom_icon_url = $custom_icon_id ? wp_get_attachment_image_url( $custom_icon_id, 'thumbnail' ) : '';

		wp_send_json_success( array(
			'slider_enabled'  => get_post_meta( $item_id, '_atx_nav_slider_enabled', true ),
			'slider_items'    => $slider_items,
			'brands_enabled'  => get_post_meta( $item_id, '_atx_nav_brands_enabled', true ),
			'brand_items'     => $brand_items,
			'custom_icon_id'  => $custom_icon_id,
			'custom_icon_url' => $custom_icon_url,
		) );
	}

	// ── AJAX: Save extras (slider, brands) for an item ──

	public function ajax_save_extras() {
		check_ajax_referer( 'atx_vb', '_wpnonce' );
		if ( ! current_user_can( Atx_Nav_Menu_Config::get( 'capability' ) ) ) wp_send_json_error();

		$item_id = intval( $_POST['item_id'] ?? 0 );
		if ( ! $item_id ) wp_send_json_error();

		// Slider
		update_post_meta( $item_id, '_atx_nav_slider_enabled', sanitize_text_field( $_POST['slider_enabled'] ?? '' ) );

		$slider_items = json_decode( wp_unslash( $_POST['slider_items'] ?? '[]' ), true );
		$clean_slides = array();
		if ( is_array( $slider_items ) ) {
			foreach ( $slider_items as $slide ) {
				$clean_slides[] = array(
					'image'          => absint( $slide['image'] ?? 0 ),
					'link'           => esc_url_raw( $slide['link'] ?? '' ),
					'badge'          => sanitize_text_field( $slide['badge'] ?? '' ),
					'title'          => sanitize_text_field( $slide['title'] ?? '' ),
					'description'    => sanitize_text_field( $slide['description'] ?? '' ),
					'original_price' => sanitize_text_field( $slide['original_price'] ?? '' ),
					'sale_price'     => sanitize_text_field( $slide['sale_price'] ?? '' ),
				);
			}
		}
		update_post_meta( $item_id, '_atx_nav_slider_items', $clean_slides );

		// Brands
		update_post_meta( $item_id, '_atx_nav_brands_enabled', sanitize_text_field( $_POST['brands_enabled'] ?? '' ) );

		$brand_items = json_decode( wp_unslash( $_POST['brand_items'] ?? '[]' ), true );
		$clean_brands = array();
		if ( is_array( $brand_items ) ) {
			foreach ( $brand_items as $brand ) {
				$clean_brands[] = array(
					'logo' => absint( $brand['logo'] ?? 0 ),
					'name' => sanitize_text_field( $brand['name'] ?? '' ),
					'link' => esc_url_raw( $brand['link'] ?? '' ),
				);
			}
		}
		update_post_meta( $item_id, '_atx_nav_brand_items', $clean_brands );

		wp_send_json_success();
	}

	// ── AJAX: Save custom icon ──

	public function ajax_save_custom_icon() {
		check_ajax_referer( 'atx_vb', '_wpnonce' );
		if ( ! current_user_can( Atx_Nav_Menu_Config::get( 'capability' ) ) ) wp_send_json_error();

		$item_id = intval( $_POST['item_id'] ?? 0 );
		$icon_id = intval( $_POST['icon_id'] ?? 0 );

		if ( ! $item_id ) wp_send_json_error();

		update_post_meta( $item_id, '_atx_nav_icon_custom', $icon_id );
		wp_send_json_success();
	}

	/**
	 * Render the isolated preview iframe page.
	 */
	public function render_preview_page() {
		check_ajax_referer( 'atx_vb', '_wpnonce' );
		if ( ! current_user_can( Atx_Nav_Menu_Config::get( 'capability' ) ) ) {
			wp_die( 'Permission denied.' );
		}

		$menu_location = self::get_requested_menu_location();
		$is_mega       = self::location_has_extension( $menu_location, 'mega-nav' );
		?>
		<!doctype html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<?php if ( $is_mega ) : ?>
				<link rel="stylesheet" href="<?php echo esc_url( Atx_Nav_Menu_Config::get( 'swiper_css_url' ) ); ?>">
				<?php foreach ( array( 'topbar', 'cta', 'dropdown', 'slider', 'brands', 'nested', 'flyout', 'utilities' ) as $file ) : ?>
					<link rel="stylesheet" href="<?php echo esc_url( Atx_Nav_Menu::get_module_url() . '/assets/css/frontend/' . $file . '.css' ); ?>">
				<?php endforeach; ?>
				<style>
					body { margin: 0; background: #fff; }
					#atx-nav-menu.atx-nav-menu {
						display: block !important;
						min-width: 0;
					}
				</style>
			<?php else : ?>
				<?php foreach ( self::get_theme_build_asset_urls( 'css' ) as $asset_url ) : ?>
					<link rel="stylesheet" href="<?php echo esc_url( $asset_url ); ?>">
				<?php endforeach; ?>
				<style>
					body { margin: 0; background: #fff; }
					.atx-vb-theme-preview { min-height: 180px; background: #050505; color: #fff; }
					.atx-vb-theme-preview .nav-dropdown,
					.atx-vb-theme-preview .child-dropdown { display: none; opacity: 1; height: auto; overflow: visible; }
					.atx-vb-theme-preview li:hover > .nav-dropdown,
					.atx-vb-theme-preview li:hover > .child-dropdown { display: block; }
					.atx-vb-theme-preview #mobile-menu { display: none; }
					.atx-vb-theme-preview #mobile-menu.is-open { display: flex; height: auto; }
					.atx-vb-theme-preview .child-dropdown.is-open { display: block; height: auto; }
					.atx-vb-theme-preview .nav-dropdown { background: rgba(26, 26, 26, 0.94); }
					.atx-vb-theme-preview .child-dropdown { background: rgba(26, 26, 26, 0.96); }
					.atx-vb-theme-preview a { cursor: pointer; }
				</style>
			<?php endif; ?>
		</head>
		<body>
			<?php echo self::render_preview_nav_html( $menu_location ); ?>
			<?php if ( $is_mega ) : ?>
				<script src="<?php echo esc_url( includes_url( 'js/jquery/jquery.min.js' ) ); ?>"></script>
				<script>
					window.atxNavMenu = <?php echo wp_json_encode( array(
						'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
						'sliderInterval'   => Atx_Nav_Menu_Config::get( 'slider_autoplay' ),
						'hoverDelay'       => Atx_Nav_Menu_Config::get( 'hover_delay' ),
						'hoverOutDelay'    => Atx_Nav_Menu_Config::get( 'hover_out_delay' ),
						'nestedHoverDelay' => Atx_Nav_Menu_Config::get( 'nested_hover_delay' ),
					) ); ?>;
				</script>
				<script src="<?php echo esc_url( Atx_Nav_Menu_Config::get( 'swiper_js_url' ) ); ?>"></script>
				<?php foreach ( array( 'core', 'toplevel', 'nested', 'flyout', 'sliders', 'keyboard' ) as $file ) : ?>
					<script src="<?php echo esc_url( Atx_Nav_Menu::get_module_url() . '/assets/js/frontend/' . $file . '.js' ); ?>"></script>
				<?php endforeach; ?>
			<?php else : ?>
				<?php foreach ( self::get_theme_build_asset_urls( 'js' ) as $asset_url ) : ?>
					<script type="module" src="<?php echo esc_url( $asset_url ); ?>"></script>
				<?php endforeach; ?>
				<script>
					window.initAtxVBPreviewMenu = function () {
						const root = document.querySelector('.atx-vb-theme-preview');
						if (!root) return;
						const button = root.querySelector('#mobile-menu-button');
						const menu = root.querySelector('#mobile-menu');
						if (button && menu && !button.dataset.atxPreviewBound) {
							button.dataset.atxPreviewBound = '1';
							button.addEventListener('click', function (event) {
								event.preventDefault();
								event.stopImmediatePropagation();
								const open = button.getAttribute('aria-expanded') === 'true';
								button.setAttribute('aria-expanded', open ? 'false' : 'true');
								menu.classList.toggle('hidden', open);
								menu.classList.toggle('is-open', !open);
								menu.style.display = open ? 'none' : 'flex';
								menu.style.height = open ? '0px' : 'auto';
							}, true);
						}
						root.querySelectorAll('.mobile-menu-item-has-children button[aria-expanded]').forEach(function (toggle) {
							if (toggle.dataset.atxPreviewBound) return;
							toggle.dataset.atxPreviewBound = '1';
							toggle.addEventListener('click', function (event) {
								event.preventDefault();
								event.stopImmediatePropagation();
								const open = toggle.getAttribute('aria-expanded') === 'true';
								const dropdown = toggle.closest('.mobile-menu-item-has-children')?.parentElement?.querySelector('.child-dropdown');
								toggle.setAttribute('aria-expanded', open ? 'false' : 'true');
								if (dropdown) {
									dropdown.classList.toggle('is-open', !open);
									dropdown.style.display = open ? 'none' : 'block';
									dropdown.style.height = open ? '0px' : 'auto';
								}
							}, true);
						});
					};
					window.initAtxVBPreviewMenu();
				</script>
			<?php endif; ?>
		</body>
		</html>
		<?php
		exit;
	}

	private static function get_requested_menu_location() {
		$locations = self::get_registered_locations();
		$requested = sanitize_key( $_REQUEST['menu_location'] ?? self::MENU_LOCATION );

		if ( isset( $locations[ $requested ] ) ) {
			return $requested;
		}

		$keys = array_keys( $locations );
		return $keys[0] ?? self::MENU_LOCATION;
	}

	private static function get_registered_locations() {
		$registered = get_registered_nav_menus();
		$assigned   = get_nav_menu_locations();
		$locations  = array();

		foreach ( $registered as $location => $label ) {
			if ( self::MENU_LOCATION === $location && ! Atx_Nav_Menu::is_enabled() ) {
				continue;
			}

			$menu_id = $assigned[ $location ] ?? 0;
			$menu    = $menu_id ? wp_get_nav_menu_object( $menu_id ) : null;

			$locations[ $location ] = array(
				'label'      => $label,
				'menu_id'    => $menu_id,
				'menu_name'  => $menu ? $menu->name : '',
				'extensions' => self::get_extensions_for_location( $location ),
			);
		}

		return apply_filters( 'atx_nav_builder_locations', $locations );
	}

	private static function get_menu_id_for_location( $menu_location ) {
		$assigned = get_nav_menu_locations();

		if ( ! empty( $assigned[ $menu_location ] ) ) {
			return absint( $assigned[ $menu_location ] );
		}

		$registered = get_registered_nav_menus();
		if ( empty( $registered[ $menu_location ] ) ) {
			return 0;
		}

		$menu_id = wp_create_nav_menu( $registered[ $menu_location ] );
		if ( is_wp_error( $menu_id ) ) {
			return 0;
		}

		$assigned[ $menu_location ] = $menu_id;
		set_theme_mod( 'nav_menu_locations', $assigned );

		return $menu_id;
	}

	private static function get_extensions_for_location( $menu_location ) {
		$extensions = array();
		if ( $menu_location === self::MENU_LOCATION ) {
			$extensions[] = 'mega-nav';
		}

		return array_values( array_unique( apply_filters( 'atx_nav_builder_extensions_for_location', $extensions, $menu_location ) ) );
	}

	private static function location_has_extension( $menu_location, $extension ) {
		return in_array( $extension, self::get_extensions_for_location( $menu_location ), true );
	}

	private static function get_preview_transient_key( $menu_location ) {
		return 'atx_nav_live_preview_' . get_current_user_id() . '_' . sanitize_key( $menu_location );
	}

	private static function is_frontend_preview_request() {
		if ( is_admin() || ( $_GET['atx_vb_preview'] ?? '' ) !== '1' ) {
			return false;
		}

		$nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) );
		return wp_verify_nonce( $nonce, 'atx_vb' )
			&& current_user_can( Atx_Nav_Menu_Config::get( 'capability' ) );
	}

	private static function get_frontend_preview_urls() {
		$urls = array();

		foreach ( array_keys( self::get_registered_locations() ) as $menu_location ) {
			$preview_url = apply_filters( 'atx_nav_builder_preview_url', home_url( '/' ), $menu_location );
			$query_args  = array(
				'atx_vb_preview' => '1',
				'menu_location'  => $menu_location,
				'_wpnonce'       => wp_create_nonce( 'atx_vb' ),
			);

			if ( self::location_has_extension( $menu_location, 'mega-nav' ) ) {
				$query_args[ Atx_Nav_Menu_Config::get( 'preview_param' ) ] = Atx_Nav_Menu_Config::get( 'preview_value' );
			}

			$urls[ $menu_location ] = add_query_arg( $query_args, $preview_url );
		}

		return $urls;
	}

	private static function render_preview_nav_html( $menu_location ) {
		$items = get_transient( self::get_preview_transient_key( $menu_location ) );

		if ( self::location_has_extension( $menu_location, 'mega-nav' ) && class_exists( 'Atx_Nav_Menu_Walker' ) ) {
			$menu_items = self::make_preview_items( $items );
			if ( empty( $menu_items ) ) {
				return '<div id="atx-nav-menu" class="atx-nav-menu"><div class="atx-nav-menu__container"><div class="atx-nav-menu__scroll-area"><ul class="atx-nav-menu__list"><li class="atx-nav-top-item" style="padding:14px;">No preview data. Click Refresh.</li></ul></div><div class="atx-nav-menu__cta-fixed"></div></div></div>';
			}

			$walker = new Atx_Nav_Menu_Walker();
			$args   = (object) array(
				'before'      => '',
				'after'       => '',
				'link_before' => '',
				'link_after'  => '',
				'walker'      => $walker,
			);

			return '<div id="atx-nav-menu" class="atx-nav-menu"><div class="atx-nav-menu__container"><div class="atx-nav-menu__scroll-area"><ul class="atx-nav-menu__list">' . $walker->walk( $menu_items, 4, $args ) . '</ul></div><div class="atx-nav-menu__cta-fixed"></div></div></div>';
		}

		$menu_items = self::make_preview_items( $items );
		if ( ! empty( $menu_items ) ) {
			return self::render_generic_theme_nav( $menu_items, $menu_location );
		}

		ob_start();
		wp_nav_menu( array(
			'theme_location' => $menu_location,
			'container'      => 'nav',
			'container_class'=> 'atx-vb-preview-nav',
			'walker'         => new Atx_Nav_Builder_Preview_Walker(),
			'fallback_cb'    => '__return_empty_string',
		) );
		return ob_get_clean();
	}

	private static function render_generic_theme_nav( $menu_items, $menu_location ) {
		$tree      = self::build_preview_tree( $menu_items );
		$site_name = get_bloginfo( 'name' ) ?: 'Site';

		ob_start();
		?>
		<div class="atx-vb-theme-preview">
			<header class="border-b border-gray-800 padding-x-default w-full relative">
				<nav class="hidden lg:block" aria-hidden="true">
					<div class="flex flex-wrap justify-between items-center py-6">
						<a class="flex title-font font-black items-center text-xl text-white no-underline" href="<?php echo esc_url( home_url( '/' ) ); ?>">
							<?php echo esc_html( $site_name ); ?>
						</a>
						<div class="items-center mt-5 lg:mt-0 justify-between flex w-auto">
							<ul class="flex flex-col items-center font-bold lg:gap-4 xl:gap-8 2xl:gap-12 lg:flex-row atx-vb-preview-nav" aria-label="<?php echo esc_attr( wp_get_nav_menu_name( $menu_location ) ?: $menu_location ); ?>">
								<?php echo self::render_generic_theme_menu_items( $tree ); ?>
							</ul>
						</div>
					</div>
				</nav>
				<div id="header-mobile" class="bg-transparent w-full z-40 lg:hidden relative" aria-hidden="true">
					<nav class="container mx-auto">
						<div class="flex flex-wrap justify-between items-center mx-auto">
							<div class="w-full py-5 flex justify-between">
								<a class="flex title-font font-black items-center text-xl text-white no-underline" href="<?php echo esc_url( home_url( '/' ) ); ?>">
									<?php echo esc_html( $site_name ); ?>
								</a>
								<button id="mobile-menu-button" type="button" aria-expanded="false" aria-label="Toggle menu" class="text-primary inline-flex items-center cursor-pointer justify-center text-sm focus:outline-none">
									<span class="hamburger-wrapper flex flex-col gap-1.5 group" aria-hidden="true">
										<span class="hamburger-line-top w-6 h-0.5 bg-white relative group-hover:bg-primary tran-smooth-color"></span>
										<span class="hamburger-line-mid w-6 h-0.5 bg-white group-hover:bg-primary tran-smooth-color"></span>
										<span class="hamburger-line-bot w-6 h-0.5 bg-white relative group-hover:bg-primary tran-smooth-color"></span>
									</span>
								</button>
							</div>
							<div id="mobile-menu" class="absolute w-[105%] top-full -left-[2.5%] right-0 items-center glass-dak justify-between flex-col hidden bg-grey-dark overflow-scroll">
								<div class="w-full px-5 flex flex-col font-medium py-2" aria-label="<?php echo esc_attr( wp_get_nav_menu_name( $menu_location ) ?: $menu_location ); ?>">
									<?php echo self::render_generic_theme_mobile_menu_items( $tree ); ?>
								</div>
							</div>
						</div>
					</nav>
				</div>
			</header>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function render_generic_theme_mobile_menu_items( $items, $depth = 0 ) {
		$html = '';

		foreach ( $items as $item ) {
			$children = $item->wpse_children ?? array();
			$title    = esc_html( $item->title );
			$url      = esc_url( $item->url ?: '#' );
			$id_attr  = esc_attr( $item->ID );
			$tag_html = self::render_preview_acf_tag( $item, $depth === 0 ? 'relative inline-block align-middle ml-1' : '-top-4 -left-4' );

			$html .= '<div class="py-3 group w-full flex justify-between flex-col overflow-hidden" data-item-id="' . $id_attr . '">';
			if ( ! empty( $children ) ) {
				$html .= '<div class="group flex items-center justify-between mobile-menu-item-has-children">';
				$html .= '<a class="font-normal hover:text-primary duration-200 transition-colors menu-item-container text-white no-underline" href="' . $url . '" data-item-id="' . $id_attr . '"><span class="group-hover:text-primary duration-200 transition-colors">' . $title . '</span>' . $tag_html . '</a>';
				$html .= '<button type="button" aria-expanded="false" aria-label="Toggle ' . esc_attr( $item->title ) . ' submenu" class="cursor-pointer inline-flex items-center justify-center text-sm focus:outline-none transitional-all duration-200">';
				$html .= self::chevron_svg( 'text-primary arrow-icon rotate-180', 18 );
				$html .= '</button></div>';
				$html .= '<div class="child-dropdown">';
				$html .= self::render_generic_theme_mobile_menu_items( $children, $depth + 1 );
				$html .= '</div>';
			} else {
				$html .= '<a class="flex justify-between text-white no-underline" href="' . $url . '" data-item-id="' . $id_attr . '">';
				$html .= '<div class="font-normal menu-item-container hover:text-primary duration-200 transition-colors">' . $title . $tag_html . '</div>';
				$html .= '</a>';
			}
			$html .= '</div>';
		}

		return $html;
	}

	private static function render_generic_theme_menu_items( $items, $depth = 0 ) {
		$html = '';

		foreach ( $items as $item ) {
			$children = $item->wpse_children ?? array();
			$title    = esc_html( $item->title );
			$url      = esc_url( $item->url ?: '#' );
			$id_attr  = esc_attr( $item->ID );

			if ( $depth === 0 ) {
				$tag_html = self::render_preview_acf_tag( $item, '-top-5 -left-5' );
				$html .= '<li class="group border-gray-light border-b last:border-b-0 last:pb-0 lg:py-0 lg:border-none transition-colors duration-300 relative" data-item-id="' . $id_attr . '">';
				$html .= '<a class="uppercase tracking-widest m-4 text-sm hover:text-primary transition-all duration-200 mx-4 inline-flex items-center relative text-white no-underline" href="' . $url . '" data-item-id="' . $id_attr . '">' . $title;
				$html .= $tag_html;
				if ( ! empty( $children ) ) {
					$html .= self::chevron_svg( 'ml-1 rotate-180 transition-transform duration-300 group-hover:rotate-0' );
				}
				$html .= '</a>';

				if ( ! empty( $children ) ) {
					$html .= '<div class="md:absolute z-10 py-5 glass-dark rounded-md nav-dropdown hidden shadow min-w-[150px] overflow-visible">';
					$html .= '<ul class="space-y-2">';
					$html .= self::render_generic_theme_menu_items( $children, 1 );
					$html .= '</ul></div>';
				}

				$html .= '</li>';
				continue;
			}

			if ( $depth === 1 ) {
				$tag_html = self::render_preview_acf_tag( $item, '-top-4 -left-4' );
				$html .= '<li class="px-6 py-2 relative' . ( ! empty( $children ) ? ' group/sub' : '' ) . '" data-item-id="' . $id_attr . '">';
				$html .= '<a class="font-semibold text-white tran-smooth-color hover:text-primary relative text-sm flex items-center title justify-between no-underline" href="' . $url . '" data-item-id="' . $id_attr . '">' . $title;
				$html .= $tag_html;
				if ( ! empty( $children ) ) {
					$html .= self::chevron_svg( 'ml-2 -rotate-90 transition-transform duration-300 group-hover/sub:rotate-0', 16 );
				}
				$html .= '</a>';

				if ( ! empty( $children ) ) {
					$html .= '<div class="md:absolute left-full -top-5 py-5 glass-dark z-10 rounded-md shadow min-w-[150px] ml-1 child-dropdown">';
					$html .= '<ul>';
					$html .= self::render_generic_theme_menu_items( $children, 2 );
					$html .= '</ul></div>';
				}

				$html .= '</li>';
				continue;
			}

			$html .= '<li class="px-6 py-1" data-item-id="' . $id_attr . '">';
			$html .= '<a class="font-semibold text-white hover:text-primary tran-smooth-color text-sm relative no-underline" href="' . $url . '" data-item-id="' . $id_attr . '">' . $title . self::render_preview_acf_tag( $item, '-top-4 -left-4' ) . '</a>';
			$html .= '</li>';
		}

		return $html;
	}

	private static function render_preview_acf_tag( $item, $position_class ) {
		$acf = is_array( $item->acf ?? null ) ? $item->acf : array();
		if ( empty( $acf['new_tag'] ) ) {
			return '';
		}

		$text = ! empty( $acf['custom_text'] ) ? $acf['custom_text'] : 'New';

		return '<div class="text-white bg-red-500 capitalize absolute px-0.5 whitespace-nowrap text-[10px] py-0.25 rounded-sm leading-none tracking-none z-10 ' . esc_attr( $position_class ) . '"><span class="text-[10px] relative tracking-none font-bold leading-none"> ' . esc_html( $text ) . ' </span></div>';
	}

	private static function build_preview_tree( $items ) {
		$tree   = array();
		$lookup = array();

		foreach ( $items as $index => $item ) {
			$items[ $index ]->wpse_children = array();
			$lookup[ $item->ID ] = $index;
		}

		foreach ( $items as $index => $item ) {
			$parent_id = absint( $item->menu_item_parent ?? 0 );
			if ( $parent_id && isset( $lookup[ $parent_id ] ) ) {
				$items[ $lookup[ $parent_id ] ]->wpse_children[] = $items[ $index ];
			} else {
				$tree[] = $items[ $index ];
			}
		}

		$sort_by_order = function( $a, $b ) {
			return ( $a->menu_order ?? 0 ) <=> ( $b->menu_order ?? 0 );
		};
		usort( $tree, $sort_by_order );
		foreach ( $items as $item ) {
			if ( ! empty( $item->wpse_children ) ) {
				usort( $item->wpse_children, $sort_by_order );
			}
		}

		return $tree;
	}

	private static function chevron_svg( $class = '', $size = 20 ) {
		return '<svg xmlns="http://www.w3.org/2000/svg" width="' . esc_attr( $size ) . '" height="' . esc_attr( $size ) . '" viewBox="0 0 20 20" fill="none" class="' . esc_attr( $class ) . '"><path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round"/></svg>';
	}

	private static function get_theme_build_asset_urls( $type ) {
		$manifest_path = get_stylesheet_directory() . '/public/build/manifest.json';
		if ( ! file_exists( $manifest_path ) ) {
			return array();
		}

		$manifest = json_decode( file_get_contents( $manifest_path ), true );
		if ( empty( $manifest ) ) {
			return array();
		}

		$base = trailingslashit( get_stylesheet_directory_uri() ) . 'public/build/';
		$urls = array();

		foreach ( array( 'resources/css/app.css', 'resources/js/app.js' ) as $entry ) {
			if ( empty( $manifest[ $entry ] ) ) {
				continue;
			}

			if ( $type === 'css' ) {
				if ( ! empty( $manifest[ $entry ]['file'] ) && substr( $manifest[ $entry ]['file'], -4 ) === '.css' ) {
					$urls[] = $base . $manifest[ $entry ]['file'];
				}
				foreach ( $manifest[ $entry ]['css'] ?? array() as $css_file ) {
					$urls[] = $base . $css_file;
				}
			} elseif ( $type === 'js' && ! empty( $manifest[ $entry ]['file'] ) && substr( $manifest[ $entry ]['file'], -3 ) === '.js' ) {
				$urls[] = $base . $manifest[ $entry ]['file'];
			}
		}

		return array_values( array_unique( $urls ) );
	}

	private static function get_acf_menu_item_field_defs() {
		$fields = array();

		if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
			return apply_filters( 'atx_nav_builder_acf_fields', $fields );
		}

		foreach ( acf_get_field_groups() as $group ) {
			if ( ! self::acf_group_targets_nav_menu_item( $group ) ) {
				continue;
			}

			foreach ( (array) acf_get_fields( $group ) as $field ) {
				if ( empty( $field['name'] ) || empty( $field['key'] ) ) {
					continue;
				}

				if ( in_array( $field['type'], array( 'tab', 'accordion', 'message' ), true ) ) {
					continue;
				}

				$fields[] = array(
					'key'               => $field['key'],
					'name'              => $field['name'],
					'label'             => $field['label'] ?: $field['name'],
					'type'              => $field['type'],
					'choices'           => $field['choices'] ?? array(),
					'default_value'     => $field['default_value'] ?? '',
					'conditional_logic' => $field['conditional_logic'] ?? 0,
					'instructions'      => $field['instructions'] ?? '',
					'required'          => ! empty( $field['required'] ),
					'placeholder'       => $field['placeholder'] ?? '',
					'prepend'           => $field['prepend'] ?? '',
					'append'            => $field['append'] ?? '',
					'multiple'          => ! empty( $field['multiple'] ),
					'allow_null'        => ! empty( $field['allow_null'] ),
					'min'               => $field['min'] ?? '',
					'max'               => $field['max'] ?? '',
					'step'              => $field['step'] ?? '',
					'return_format'     => $field['return_format'] ?? '',
					'ui'                => ! empty( $field['ui'] ),
				);
			}
		}

		return apply_filters( 'atx_nav_builder_acf_fields', $fields );
	}

	private static function acf_group_targets_nav_menu_item( $group ) {
		foreach ( (array) ( $group['location'] ?? array() ) as $rules ) {
			foreach ( (array) $rules as $rule ) {
				if ( ( $rule['param'] ?? '' ) === 'nav_menu_item' ) {
					return true;
				}
			}
		}

		return false;
	}

	private static function get_acf_menu_item_values( $item_id ) {
		$values = array();
		foreach ( self::get_acf_menu_item_field_defs() as $field ) {
			if ( function_exists( 'get_field' ) ) {
				$value = get_field( $field['name'], $item_id, false );
			} else {
				$value = get_post_meta( $item_id, $field['name'], true );
			}

			$values[ $field['name'] ] = self::normalize_acf_value_for_builder( $value, $field );
		}

		return $values;
	}

	private static function save_acf_menu_item_values( $item_id, $values ) {
		foreach ( self::get_acf_menu_item_field_defs() as $field ) {
			$name = $field['name'];
			if ( ! array_key_exists( $name, $values ) ) {
				continue;
			}

			$value = self::sanitize_acf_value_from_builder( $values[ $name ], $field );

			if ( function_exists( 'update_field' ) ) {
				update_field( $field['key'], $value, $item_id );
			} else {
				update_post_meta( $item_id, $name, $value );
			}
		}
	}

	private static function validate_required_acf_values( $items ) {
		$fields          = self::get_acf_menu_item_field_defs();
		$editable_fields = array_filter( $fields, function( $field ) {
			return ! empty( $field['required'] ) && ! in_array( $field['type'], array( 'group', 'repeater', 'flexible_content', 'clone', 'google_map' ), true );
		} );

		if ( empty( $editable_fields ) ) {
			return '';
		}

		foreach ( $items as $item ) {
			$acf = is_array( $item['acf'] ?? null ) ? $item['acf'] : array();

			foreach ( $editable_fields as $field ) {
				if ( ! self::acf_field_visible_for_values( $field, $acf, $fields ) ) {
					continue;
				}

				$value = $acf[ $field['name'] ] ?? ( $field['default_value'] ?? '' );
				if ( self::acf_value_is_empty( $value, $field ) ) {
					$title = sanitize_text_field( $item['title'] ?? __( 'Menu item', 'atx_theme' ) );
					return sprintf(
						/* translators: 1: menu item title, 2: field label */
						__( '%1$s needs %2$s before saving.', 'atx_theme' ),
						$title,
						$field['label'] ?: $field['name']
					);
				}
			}
		}

		return '';
	}

	private static function acf_field_visible_for_values( $field, $values, $fields ) {
		if ( empty( $field['conditional_logic'] ) || ! is_array( $field['conditional_logic'] ) ) {
			return true;
		}

		foreach ( $field['conditional_logic'] as $group ) {
			$group_matches = true;
			foreach ( (array) $group as $rule ) {
				$controller = self::find_acf_field_by_key_or_name( $fields, $rule['field'] ?? '' );
				if ( ! $controller ) {
					continue;
				}

				$value    = $values[ $controller['name'] ] ?? ( $controller['default_value'] ?? '' );
				$operator = $rule['operator'] ?? '==';
				$expected = $rule['value'] ?? '';

				if ( ! self::acf_rule_matches( $value, $operator, $expected ) ) {
					$group_matches = false;
					break;
				}
			}

			if ( $group_matches ) {
				return true;
			}
		}

		return false;
	}

	private static function find_acf_field_by_key_or_name( $fields, $key_or_name ) {
		foreach ( $fields as $field ) {
			if ( ( $field['key'] ?? '' ) === $key_or_name || ( $field['name'] ?? '' ) === $key_or_name ) {
				return $field;
			}
		}

		return null;
	}

	private static function acf_rule_matches( $value, $operator, $expected ) {
		if ( is_array( $value ) ) {
			$contains = in_array( (string) $expected, array_map( 'strval', $value ), true );
			return $operator === '!=' ? ! $contains : $contains;
		}

		$actual = is_array( $value ) ? '' : (string) $value;
		$target = (string) $expected;

		return $operator === '!=' ? $actual !== $target : $actual === $target;
	}

	private static function acf_value_is_empty( $value, $field ) {
		if ( $field['type'] === 'true_false' ) {
			return empty( $value );
		}

		if ( in_array( $field['type'], array( 'image', 'file' ), true ) ) {
			return empty( is_array( $value ) ? ( $value['id'] ?? '' ) : $value );
		}

		if ( $field['type'] === 'link' ) {
			return empty( is_array( $value ) ? ( $value['url'] ?? '' ) : $value );
		}

		if ( is_array( $value ) ) {
			return empty( array_filter( $value ) );
		}

		return trim( (string) $value ) === '';
	}

	private static function normalize_acf_value_for_builder( $value, $field ) {
		if ( $field['type'] === 'true_false' ) {
			return $value ? '1' : '';
		}

		if ( in_array( $field['type'], array( 'image', 'file' ), true ) ) {
			$attachment_id = absint( $value );
			if ( ! $attachment_id ) {
				return array( 'id' => 0, 'url' => '', 'title' => '' );
			}

			$url = $field['type'] === 'image'
				? wp_get_attachment_image_url( $attachment_id, 'thumbnail' )
				: wp_get_attachment_url( $attachment_id );

			return array(
				'id'    => $attachment_id,
				'url'   => $url ?: '',
				'title' => get_the_title( $attachment_id ),
			);
		}

		if ( $field['type'] === 'gallery' ) {
			$gallery = array();
			foreach ( (array) $value as $attachment_id ) {
				$attachment_id = absint( is_array( $attachment_id ) ? ( $attachment_id['ID'] ?? $attachment_id['id'] ?? 0 ) : $attachment_id );
				if ( ! $attachment_id ) {
					continue;
				}

				$gallery[] = array(
					'id'    => $attachment_id,
					'url'   => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) ?: '',
					'title' => get_the_title( $attachment_id ),
				);
			}

			return $gallery;
		}

		if ( is_object( $value ) ) {
			if ( isset( $value->ID ) ) {
				return absint( $value->ID );
			}

			return wp_json_encode( $value );
		}

		if ( is_array( $value ) ) {
			return array_map( function( $entry ) use ( $field ) {
				return self::normalize_acf_value_for_builder( $entry, array( 'type' => $field['type'] ) );
			}, $value );
		}

		return $value;
	}

	private static function sanitize_acf_value_from_builder( $value, $field ) {
		$type = $field['type'] ?? 'text';

		switch ( $type ) {
			case 'true_false':
				return ! empty( $value ) ? 1 : 0;

			case 'number':
			case 'range':
				return is_numeric( $value ) ? $value : '';

			case 'email':
				return sanitize_email( $value );

			case 'url':
			case 'page_link':
				return esc_url_raw( $value );

			case 'textarea':
				return sanitize_textarea_field( $value );

			case 'image':
			case 'file':
				if ( is_array( $value ) ) {
					return absint( $value['id'] ?? 0 );
				}

				return absint( $value );

			case 'gallery':
				return array_values( array_filter( array_map( function( $entry ) {
					return absint( is_array( $entry ) ? ( $entry['id'] ?? 0 ) : $entry );
				}, (array) $value ) ) );

			case 'link':
				$link = is_array( $value ) ? $value : array();
				return array(
					'url'    => esc_url_raw( $link['url'] ?? '' ),
					'title'  => sanitize_text_field( $link['title'] ?? '' ),
					'target' => sanitize_key( $link['target'] ?? '' ),
				);

			case 'checkbox':
			case 'select':
			case 'post_object':
			case 'relationship':
			case 'taxonomy':
			case 'user':
				if ( is_array( $value ) ) {
					return array_map( 'sanitize_text_field', $value );
				}

				return sanitize_text_field( $value );

			default:
				if ( is_array( $value ) ) {
					return map_deep( $value, 'sanitize_text_field' );
				}

				return sanitize_text_field( $value );
		}
	}

	private static function make_preview_items( $items ) {
		if ( empty( $items ) || ! is_array( $items ) ) {
			return array();
		}

		$menu_items = array();
		foreach ( $items as $item ) {
			$obj = new stdClass();
			$obj->ID               = absint( $item['id'] );
			$obj->db_id            = absint( $item['id'] );
			$obj->title            = wp_specialchars_decode( sanitize_text_field( $item['title'] ?? '' ), ENT_QUOTES );
			$obj->url              = esc_url_raw( $item['url'] ?? '#' ) ?: '#';
			$obj->menu_item_parent = absint( $item['parent_id'] ?? 0 );
			$obj->menu_order       = absint( $item['position'] ?? 0 );
			$obj->classes          = array_filter( (array) ( $item['classes'] ?? array() ) );
			$obj->type             = sanitize_key( $item['type'] ?? 'custom' );
			$obj->object           = sanitize_key( $item['object'] ?? 'custom' );
			$obj->object_id        = absint( $item['object_id'] ?? $obj->ID );
			$obj->acf              = is_array( $item['acf'] ?? null ) ? $item['acf'] : array();
			$obj->target           = '';
			$obj->attr_title       = '';
			$obj->description      = '';
			$obj->xfn              = '';

			foreach ( $items as $check ) {
				if ( absint( $check['parent_id'] ?? 0 ) === $obj->ID ) {
					$obj->classes[] = 'menu-item-has-children';
					break;
				}
			}

			$menu_items[] = $obj;
		}

		return $menu_items;
	}
}
