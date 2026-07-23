<?php
/**
 * Atx Nav Menu - Icon Picker for Menu Items
 *
 * Adds an icon selection field to the WordPress menu item editor.
 * Icons are loaded from: assets/icons/*.svg
 * Admins can also upload a custom icon via the media library.
 *
 * To add a new icon: drop an SVG file into assets/icons/
 * The filename becomes the key, e.g. "cooker.svg" → "cooker"
 * The label is generated from the filename: "cooker" → "Cooker"
 *
 * @package Atx
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Atx_Nav_Menu_Icons' ) ) {

	class Atx_Nav_Menu_Icons {

		const META_KEY        = '_atx_nav_icon';
		const CUSTOM_META_KEY = '_atx_nav_icon_custom';

		/**
		 * Cached icons array
		 */
		private static $icons_cache = null;

		public function __construct() {
			add_action( 'wp_nav_menu_item_custom_fields', array( $this, 'render_icon_field' ), 10, 5 );
			add_action( 'wp_update_nav_menu_item', array( $this, 'save_icon_field' ), 10, 3 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		}

		/**
		 * Get the icons directory path
		 */
		public static function get_icons_path() {
			return Atx_Nav_Menu::get_module_path() . '/assets/icons';
		}

		/**
		 * Get the icons directory URL
		 */
		public static function get_icons_url() {
			return Atx_Nav_Menu::get_module_url() . '/assets/icons';
		}

		/**
		 * Scan assets/icons/ and build the icons list.
		 * Drop any .svg file in that folder and it auto-appears in the picker.
		 *
		 * @return array icon_key => array( 'label' => 'Label', 'svg' => '<svg>...</svg>', 'url' => '...icons/file.svg' )
		 */
		public static function get_predefined_icons() {
			if ( self::$icons_cache !== null ) {
				return self::$icons_cache;
			}

			$icons = array();
			$dir   = self::get_icons_path();
			$url   = self::get_icons_url();

			if ( ! is_dir( $dir ) ) {
				self::$icons_cache = $icons;
				return $icons;
			}

			$files = glob( $dir . '/*.svg' );
			if ( empty( $files ) ) {
				self::$icons_cache = $icons;
				return $icons;
			}

			sort( $files );

			foreach ( $files as $file ) {
				$filename = basename( $file, '.svg' );
				$svg      = self::sanitize_svg( file_get_contents( $file ) );

				if ( empty( $svg ) ) {
					continue;
				}

				// Generate a readable label from filename: "fridge-freezer" → "Fridge Freezer"
				$label = ucwords( str_replace( array( '-', '_' ), ' ', $filename ) );

				$icons[ $filename ] = array(
					'label' => $label,
					'svg'   => $svg,
					'url'   => $url . '/' . basename( $file ),
				);
			}

			self::$icons_cache = apply_filters( 'atx_nav_menu_predefined_icons', $icons );
			return self::$icons_cache;
		}

		/**
		 * Render the icon picker field in the menu item editor
		 */
		public function render_icon_field( $item_id, $menu_item, $depth, $args, $current_object_id = 0 ) {
			echo Atx_Nav_Menu::get_template( 'admin/icon-field', array(
				'item_id'       => $item_id,
				'selected_icon' => get_post_meta( $item_id, self::META_KEY, true ),
				'custom_icon'   => get_post_meta( $item_id, self::CUSTOM_META_KEY, true ),
				'custom_url'    => get_post_meta( $item_id, self::CUSTOM_META_KEY, true )
					? wp_get_attachment_image_url( get_post_meta( $item_id, self::CUSTOM_META_KEY, true ), 'thumbnail' )
					: '',
				'icons'         => self::get_predefined_icons(),
			), false );
		}

		/**
		 * Save the icon field when menu item is saved
		 */
		public function save_icon_field( $menu_id, $menu_item_db_id, $menu_item_args ) {
			if ( isset( $_POST['atx_nav_icon'][ $menu_item_db_id ] ) ) {
				$icon = sanitize_text_field( $_POST['atx_nav_icon'][ $menu_item_db_id ] );
				update_post_meta( $menu_item_db_id, self::META_KEY, $icon );
			}

			if ( isset( $_POST['atx_nav_icon_custom'][ $menu_item_db_id ] ) ) {
				$custom = absint( $_POST['atx_nav_icon_custom'][ $menu_item_db_id ] );
				update_post_meta( $menu_item_db_id, self::CUSTOM_META_KEY, $custom );
			}
		}

		/**
		 * Enqueue assets on the nav-menus admin page
		 */
		public function enqueue_admin_assets( $hook ) {
			if ( $hook !== 'nav-menus.php' ) {
				return;
			}

			wp_enqueue_script(
				'atx-nav-admin-icons',
				Atx_Nav_Menu::get_module_url() . '/assets/js/admin/icons.js',
				array( 'jquery', 'atx-nav-admin-picker' ),
				Atx_Nav_Menu::VERSION,
				true
			);

			wp_localize_script( 'atx-nav-admin-icons', 'atxNavIcons', self::get_predefined_icons() );
		}

		/**
		 * Sanitize SVG content — strip scripts, event handlers, and dangerous elements.
		 *
		 * @param string $svg Raw SVG content
		 * @return string Sanitized SVG
		 */
		private static function sanitize_svg( $svg ) {
			if ( empty( $svg ) ) return '';

			// Remove script tags and their contents
			$svg = preg_replace( '/<script\b[^>]*>.*?<\/script>/is', '', $svg );

			// Remove on* event attributes (onclick, onload, onerror, etc.)
			$svg = preg_replace( '/\s+on\w+\s*=\s*["\'][^"\']*["\']/i', '', $svg );

			// Remove javascript: URLs
			$svg = preg_replace( '/href\s*=\s*["\']javascript:[^"\']*["\']/i', '', $svg );
			$svg = preg_replace( '/xlink:href\s*=\s*["\']javascript:[^"\']*["\']/i', '', $svg );

			// Remove data: URLs (can contain scripts)
			$svg = preg_replace( '/href\s*=\s*["\']data:[^"\']*["\']/i', '', $svg );

			// Remove foreignObject, use (can reference external resources)
			$svg = preg_replace( '/<foreignObject\b[^>]*>.*?<\/foreignObject>/is', '', $svg );

			// Remove set and animate elements that could trigger scripts
			$svg = preg_replace( '/<set\b[^>]*>/i', '', $svg );

			return trim( $svg );
		}

		/**
		 * Get the icon HTML for a menu item
		 *
		 * @param int $item_id Menu item post ID
		 * @return string HTML for the icon, or empty string
		 */
		public static function get_icon_html( $item_id ) {
			$icon_key = get_post_meta( $item_id, self::META_KEY, true );

			if ( empty( $icon_key ) ) {
				return '';
			}

			if ( $icon_key === 'custom' ) {
				$custom_id = get_post_meta( $item_id, self::CUSTOM_META_KEY, true );
				if ( $custom_id ) {
					$url = wp_get_attachment_image_url( $custom_id, 'thumbnail' );
					if ( $url ) {
						return '<img src="' . esc_url( $url ) . '" class="atx-nav-mega-category__icon atx-nav-mega-category__icon--custom" alt="" loading="lazy" /> ';
					}
				}
				return '';
			}

			$icons = self::get_predefined_icons();
			if ( isset( $icons[ $icon_key ] ) ) {
				return '<span class="atx-nav-mega-category__icon">' . $icons[ $icon_key ]['svg'] . '</span> ';
			}

			return '';
		}

	}
}
