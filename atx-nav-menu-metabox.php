<?php
/**
 * Atx Nav Menu - Admin Fields for Menu Items
 *
 * Adds slider and brand configuration directly to top-level
 * menu items in the WordPress menu editor using native WordPress hooks.
 * This does not require the Meta Box plugin.
 *
 * @package Atx
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Atx_Nav_Menu_Admin_Fields' ) ) {

	class Atx_Nav_Menu_Admin_Fields {

		const SLIDER_ENABLED_KEY = '_atx_nav_slider_enabled';
		const SLIDER_ITEMS_KEY   = '_atx_nav_slider_items';
		const BRANDS_ENABLED_KEY = '_atx_nav_brands_enabled';
		const BRAND_ITEMS_KEY    = '_atx_nav_brand_items';

		public function __construct() {
			add_action( 'wp_nav_menu_item_custom_fields', array( $this, 'render_fields' ), 20, 5 );
			add_action( 'wp_update_nav_menu_item', array( $this, 'save_fields' ), 10, 3 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
			add_action( 'wp_ajax_atx_nav_get_images', array( $this, 'ajax_get_images' ) );
			add_action( 'wp_ajax_atx_nav_upload_image', array( $this, 'ajax_upload_image' ) );
			add_action( 'wp_ajax_atx_nav_preview_save', array( $this, 'ajax_preview_save' ) );
			add_action( 'admin_footer', array( $this, 'output_admin_templates' ) );
		}

		/**
		 * Enqueue assets on nav-menus page
		 */
		public function enqueue_admin_assets( $hook ) {
			if ( $hook !== 'nav-menus.php' ) {
				return;
			}

			$base = Atx_Nav_Menu::get_module_url() . '/assets';
			$ver  = Atx_Nav_Menu::VERSION;

			// Admin CSS
			wp_enqueue_style( 'atx-nav-admin-css', $base . '/css/admin/atx-nav-menu-admin.css', array(), $ver );

			// Admin JS modules
			$admin_js = array(
				'core'   => array( 'jquery' ),
				'picker' => array( 'atx-nav-admin-core' ),
				'slides' => array( 'atx-nav-admin-picker' ),
				'brands' => array( 'atx-nav-admin-picker' ),
			);
			foreach ( $admin_js as $file => $deps ) {
				wp_enqueue_script( 'atx-nav-admin-' . $file, $base . '/js/admin/' . $file . '.js', $deps, $ver, true );
			}

			wp_enqueue_script( 'atx-nav-admin-collapse', $base . '/js/admin/collapse.js', array( 'jquery' ), $ver, true );
			wp_enqueue_script( 'atx-nav-admin-preview', $base . '/js/admin/preview.js', array( 'jquery' ), $ver, true );
			wp_localize_script( 'atx-nav-admin-preview', 'atxNavPreview', array(
				'siteUrl' => home_url( '/' ),
			) );

			wp_localize_script( 'atx-nav-admin-core', 'atxNavAdmin', array(
				'nonce' => wp_create_nonce( 'atx_nav_admin' ),
			) );
		}

		/**
		 * Render slider and brand fields on menu items (depth 0 only)
		 */
		public function render_fields( $item_id, $menu_item, $depth, $args, $current_object_id = 0 ) {
			$show_fields = false;

			if ( $depth === 0 ) {
				$classes = is_array( $menu_item->classes ) ? $menu_item->classes : array();
				$is_nested = in_array( 'atx-nested-nav', $classes );

				// Show "Secondary Navigation" indicator for nested nav items
				if ( $is_nested ) {
					echo Atx_Nav_Menu::get_template( 'admin/nested-indicator', array(), false );
				}

				// Show slider/brands only on non-nested top-level items
				// (nested items like Built-In Appliances get slider/brands on their tab children instead)
				if ( ! $is_nested ) {
					$show_fields = true;
				}
			} elseif ( $depth === 1 ) {
				// Show fields on depth-1 items if parent has atx-nested-nav class
				$parent_id = intval( $menu_item->menu_item_parent );
				if ( $parent_id ) {
					$parent_classes = get_post_meta( $parent_id, '_menu_item_classes', true );
					if ( is_array( $parent_classes ) && in_array( 'atx-nested-nav', $parent_classes ) ) {
						$show_fields = true;
					}
				}
			}

			if ( ! $show_fields ) {
				return;
			}

			$slider_items = get_post_meta( $item_id, self::SLIDER_ITEMS_KEY, true );
			$brand_items  = get_post_meta( $item_id, self::BRAND_ITEMS_KEY, true );

			echo Atx_Nav_Menu::get_template( 'admin/extras-field', array(
				'item_id'        => $item_id,
				'slider_enabled' => get_post_meta( $item_id, self::SLIDER_ENABLED_KEY, true ),
				'slider_items'   => is_array( $slider_items ) ? $slider_items : array(),
				'brands_enabled' => get_post_meta( $item_id, self::BRANDS_ENABLED_KEY, true ),
				'brand_items'    => is_array( $brand_items ) ? $brand_items : array(),
			), false );
		}

		/**
		 * Render a single slide row from template
		 */
		private static function render_slide_row( $item_id, $i, $slide ) {
			echo Atx_Nav_Menu::get_template( 'admin/slide-row', array(
				'item_id' => $item_id,
				'i'       => $i,
				'slide'   => $slide,
			), false );
		}

		/**
		 * Render a single brand row from template
		 */
		private static function render_brand_row( $item_id, $i, $brand ) {
			echo Atx_Nav_Menu::get_template( 'admin/brand-row', array(
				'item_id' => $item_id,
				'i'       => $i,
				'brand'   => $brand,
			), false );
		}

		/**
		 * Save slider and brand fields
		 */
		public function save_fields( $menu_id, $menu_item_db_id, $menu_item_args ) {
			// Skip when called from Visual Builder AJAX (no slider/brand data in request)
			if ( ! empty( $_POST['action'] ) && strpos( $_POST['action'], 'atx_vb_' ) === 0 ) {
				return;
			}

			// Slider enabled
			$slider_enabled = isset( $_POST['atx_slider_enabled'][ $menu_item_db_id ] ) ? '1' : '';
			update_post_meta( $menu_item_db_id, self::SLIDER_ENABLED_KEY, $slider_enabled );

			// Slider items
			$slider_items = array();
			if ( ! empty( $_POST['atx_slider'][ $menu_item_db_id ] ) ) {
				foreach ( $_POST['atx_slider'][ $menu_item_db_id ] as $slide ) {
					if ( ! empty( $slide['image'] ) || ! empty( $slide['link'] ) ) {
						$slider_items[] = array(
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
			}
			update_post_meta( $menu_item_db_id, self::SLIDER_ITEMS_KEY, $slider_items );

			// Brands enabled
			$brands_enabled = isset( $_POST['atx_brands_enabled'][ $menu_item_db_id ] ) ? '1' : '';
			update_post_meta( $menu_item_db_id, self::BRANDS_ENABLED_KEY, $brands_enabled );

			// Brand items
			$brand_items = array();
			if ( ! empty( $_POST['atx_brands'][ $menu_item_db_id ] ) ) {
				foreach ( $_POST['atx_brands'][ $menu_item_db_id ] as $brand ) {
					if ( ! empty( $brand['logo'] ) || ! empty( $brand['name'] ) ) {
						$brand_items[] = array(
							'logo' => absint( $brand['logo'] ?? 0 ),
							'name' => sanitize_text_field( $brand['name'] ?? '' ),
							'link' => esc_url_raw( $brand['link'] ?? '' ),
						);
					}
				}
			}
			update_post_meta( $menu_item_db_id, self::BRAND_ITEMS_KEY, $brand_items );
		}

		/**
		 * Output HTML templates as <script type="text/html"> blocks in admin footer
		 */
		public function output_admin_templates() {
			$screen = get_current_screen();
			if ( ! $screen || $screen->id !== 'nav-menus' ) {
				return;
			}

			$templates_dir = Atx_Nav_Menu::get_module_path() . '/assets/js/admin/templates';
			$templates = array(
				'atx-tmpl-slide-row'      => 'slide-row.html',
				'atx-tmpl-brand-row'      => 'brand-row.html',
				'atx-tmpl-image-picker'   => 'image-picker.html',
				'atx-tmpl-preview-modal'  => 'preview-modal.html',
			);

			foreach ( $templates as $id => $file ) {
				$path = $templates_dir . '/' . $file;
				if ( file_exists( $path ) ) {
					echo '<script type="text/html" id="' . esc_attr( $id ) . '">';
					echo file_get_contents( $path );
					echo '</script>' . "\n";
				}
			}
		}

		/**
		 * AJAX: Save live preview data as a transient
		 */
		public function ajax_preview_save() {
			// Accept nonce from either admin or visual builder
			if ( ! wp_verify_nonce( $_REQUEST['_wpnonce'] ?? '', 'atx_nav_admin' ) &&
			     ! wp_verify_nonce( $_REQUEST['_wpnonce'] ?? '', 'atx_vb' ) ) {
				wp_send_json_error( 'Invalid nonce.' );
			}
			if ( ! current_user_can( Atx_Nav_Menu_Config::get( 'capability' ) ) ) wp_send_json_error();

			$items = json_decode( wp_unslash( $_POST['items'] ?? '[]' ), true );
			if ( empty( $items ) ) wp_send_json_error( 'No items.' );

			// Sanitize each item before storing
			$clean_items = array();
			foreach ( $items as $item ) {
				$clean_items[] = array(
					'id'        => absint( $item['id'] ?? 0 ),
					'title'     => sanitize_text_field( $item['title'] ?? '' ),
					'url'       => esc_url_raw( $item['url'] ?? '#' ),
					'parent_id' => absint( $item['parent_id'] ?? 0 ),
					'position'  => absint( $item['position'] ?? 0 ),
					'classes'   => array_map( 'sanitize_html_class', (array) ( $item['classes'] ?? array() ) ),
					'icon'      => sanitize_text_field( $item['icon'] ?? '' ),
				);
			}

			set_transient( 'atx_nav_live_preview', $clean_items, Atx_Nav_Menu_Config::get( 'preview_ttl' ) );
			wp_send_json_success();
		}

		/**
		 * AJAX: Get images from media library
		 */
		public function ajax_get_images() {
			check_ajax_referer( 'atx_nav_admin', '_wpnonce' );

			if ( ! current_user_can( 'upload_files' ) ) {
				wp_send_json_error();
			}

			$search   = sanitize_text_field( $_POST['search'] ?? '' );
			$page     = absint( $_POST['page'] ?? 1 );
			$per_page = 30;

			$args = array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'post_status'    => 'inherit',
				'posts_per_page' => $per_page + 1,
				'paged'          => $page,
				'orderby'        => 'date',
				'order'          => 'DESC',
			);

			if ( $search ) {
				$args['s'] = $search;
			}

			$query  = new WP_Query( $args );
			$images = array();

			foreach ( $query->posts as $i => $post ) {
				if ( $i >= $per_page ) break;

				$thumb = wp_get_attachment_image_url( $post->ID, 'thumbnail' );
				$url   = wp_get_attachment_image_url( $post->ID, 'medium' );

				$images[] = array(
					'id'    => $post->ID,
					'thumb' => $thumb ?: '',
					'url'   => $url ?: '',
					'title' => $post->post_title,
				);
			}

			wp_send_json_success( array(
				'images'   => $images,
				'has_more' => count( $query->posts ) > $per_page,
			) );
		}

		/**
		 * AJAX: Upload an image to the media library
		 */
		public function ajax_upload_image() {
			check_ajax_referer( 'atx_nav_admin', '_wpnonce' );

			if ( ! current_user_can( 'upload_files' ) ) {
				wp_send_json_error( 'Permission denied.' );
			}

			if ( empty( $_FILES['file'] ) ) {
				wp_send_json_error( 'No file uploaded.' );
			}

			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';

			$attachment_id = media_handle_upload( 'file', 0 );

			if ( is_wp_error( $attachment_id ) ) {
				wp_send_json_error( $attachment_id->get_error_message() );
			}

			$url = wp_get_attachment_image_url( $attachment_id, 'medium' );

			wp_send_json_success( array(
				'id'  => $attachment_id,
				'url' => $url,
			) );
		}

		/**
		 * Get slider items for a menu item
		 */
		public static function get_slider_items( $item_id ) {
			$enabled = get_post_meta( $item_id, self::SLIDER_ENABLED_KEY, true );
			if ( ! $enabled ) {
				return array();
			}
			$items = get_post_meta( $item_id, self::SLIDER_ITEMS_KEY, true );
			return is_array( $items ) ? $items : array();
		}

		/**
		 * Get brand items for a menu item
		 */
		public static function get_brand_items( $item_id ) {
			$enabled = get_post_meta( $item_id, self::BRANDS_ENABLED_KEY, true );
			if ( ! $enabled ) {
				return array();
			}
			$items = get_post_meta( $item_id, self::BRAND_ITEMS_KEY, true );
			return is_array( $items ) ? $items : array();
		}
	}
}

if ( ! class_exists( 'Atx_Nav_Menu_MetaBox' ) && class_exists( 'Atx_Nav_Menu_Admin_Fields' ) ) {
	class_alias( 'Atx_Nav_Menu_Admin_Fields', 'Atx_Nav_Menu_MetaBox' );
}
