<?php
/**
 * Atx Nav Menu - Configuration
 *
 * Central config for all module settings. Override via filters or
 * define constants in functions.php before the module loads.
 *
 * @package Atx
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Atx_Nav_Menu_Config {

	/**
	 * Get a config value. Checks for constant override, then filter, then default.
	 *
	 * @param string $key Config key
	 * @return mixed
	 */
	public static function get( $key ) {
		$defaults = self::defaults();

		if ( ! isset( $defaults[ $key ] ) ) {
			return null;
		}

		$value = $defaults[ $key ];

		// Check for constant override (e.g. ATX_NAV_CAPABILITY => 'atx_nav_capability')
		$const = 'ATX_NAV_' . strtoupper( $key );
		if ( defined( $const ) ) {
			$value = constant( $const );
		}

		// Allow filter override
		return apply_filters( 'atx_nav_config_' . $key, $value );
	}

	/**
	 * All default configuration values
	 */
	private static function defaults() {
		return array(

			// ── Permissions ──
			// Capability required to manage the mega nav (admin pages, AJAX, etc.)
			'capability'          => 'edit_theme_options',

			// Capability required to upload images (slider, brands, icons)
			'upload_capability'   => 'upload_files',

			// ── Menu ──
			// Set to true (or define ATX_NAV_V2_ENABLED as true) to register and render Primary V2.
			'v2_enabled'         => false,
			'menu_location'       => 'primary-v2',
			'menu_location_label' => 'Primary V2 (New Mega Nav)',

			// ── Preview ──
			'preview_param'       => 'nav',
			'preview_value'       => 'v2',
			'preview_transient'   => 'atx_nav_live_preview',
			'preview_ttl'         => 300, // seconds (5 minutes)

			// ── Meta Keys ──
			'meta_icon'           => '_atx_nav_icon',
			'meta_icon_custom'    => '_atx_nav_icon_custom',
			'meta_slider_enabled' => '_atx_nav_slider_enabled',
			'meta_slider_items'   => '_atx_nav_slider_items',
			'meta_brands_enabled' => '_atx_nav_brands_enabled',
			'meta_brand_items'    => '_atx_nav_brand_items',
			'meta_default_image'  => '_atx_nav_default_image',
			'meta_imported_file'  => '_atx_nav_imported_file',

			// ── Nonces ──
			'nonce_admin'         => 'atx_nav_admin',
			'nonce_vb'            => 'atx_vb',

			// ── Export ──
			'export_dir'          => '/assets/exports/',

			// ── CSS Classes (for walker/builder) ──
			'class_cta'           => 'atx-nav-cta',
			'class_nested'        => 'atx-nested-nav',
			'class_no_extras'     => 'atx-no-extras',
			'class_placeholder'   => 'atx-placeholder',
			'class_col_break'     => 'atx-col-break',
			'class_cols_2'        => 'atx-cols-2',
			'class_cols_3'        => 'atx-cols-3',
			'class_cols_4'        => 'atx-cols-4',
			'class_flyout'        => 'atx-flyout',

			// ── Frontend ──
			'slider_autoplay'     => 5000, // ms between slides
			'hover_delay'         => 200,  // ms before dropdown opens
			'hover_out_delay'     => 300,  // ms before dropdown closes
			'nested_hover_delay'  => 150,  // ms before nested tab switches

			// ── External CDN ──
			'swiper_css_url'      => 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css',
			'swiper_js_url'       => 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js',
			'swiper_version'      => '11.0.0',
			'google_fonts_url'    => 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',

			// ── Images ──
			'max_images_per_page' => 30, // AJAX image picker grid
		);
	}
}
