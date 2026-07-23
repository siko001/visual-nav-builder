<?php
/**
 * Plugin Name: ATX Nav Visual Builder
 * Description: Build registered WordPress menu locations visually against a live, interactive website preview.
 * Version: 1.1.7
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: ATX - Neil VM
 * Author URI: https://neilmallia.com
 * License: GPL-2.0-or-later
 * Text Domain: atx-nav-visual-builder
 *
 * Atx Nav Menu - Main Module Loader
 *
 * Registers the primary-v2 menu location, enqueues assets,
 * and loads all sub-modules for the new mega navigation.
 *
 * @package Atx
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ATX_VISUAL_NAV_BUILDER_FILE', __FILE__ );
define( 'ATX_VISUAL_NAV_BUILDER_DIR', __DIR__ );
define( 'ATX_VISUAL_NAV_BUILDER_URL', plugin_dir_url( __FILE__ ) );

require_once ATX_VISUAL_NAV_BUILDER_DIR . '/src/Support/GitHubPluginUpdater.php';

add_action( 'plugins_loaded', static function (): void {
	( new \AtxVisualNavBuilder\Support\GitHubPluginUpdater(
		ATX_VISUAL_NAV_BUILDER_FILE,
		ATX_VISUAL_NAV_BUILDER_DIR
	) )->register();
} );

if ( ! class_exists( 'Atx_Nav_Menu' ) ) {

	class Atx_Nav_Menu {

		/**
		 * Module version
		 */
		const VERSION = '1.1.7';

		/**
		 * Menu location identifier — use config value
		 */
		const MENU_LOCATION = 'primary-v2';

		/**
		 * Singleton instance
		 */
		private static $instance = null;

		/**
		 * Get or create the singleton instance
		 */
		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Get the menu location from config
		 */
		public static function get_menu_location() {
			return Atx_Nav_Menu_Config::get( 'menu_location' );
		}

		/**
		 * Get the required capability from config
		 */
		public static function get_capability() {
			return Atx_Nav_Menu_Config::get( 'capability' );
		}

		/**
		 * Whether the Primary V2 feature is enabled in code.
		 */
		public static function is_enabled() {
			return (bool) Atx_Nav_Menu_Config::get( 'v2_enabled' );
		}

		/**
		 * Path to this module directory
		 */
		private static $module_path;

		/**
		 * URL to this module directory
		 */
		private static $module_url;

		/**
		 * Constructor - wire everything up (private for singleton)
		 */
		private function __construct() {
			self::$module_path = dirname( __FILE__ );
			self::$module_url  = untrailingslashit( plugin_dir_url( __FILE__ ) );

			// Load config first
			require_once self::$module_path . '/atx-nav-menu-config.php';

			// Load sub-modules
			require_once self::$module_path . '/atx-nav-menu-metabox.php';
			require_once self::$module_path . '/atx-nav-menu-icons.php';
			require_once self::$module_path . '/atx-nav-menu-walker.php';
			require_once self::$module_path . '/atx-nav-menu-render.php';
			require_once self::$module_path . '/atx-nav-menu-export.php';
			require_once self::$module_path . '/visual-builder/visual-builder.php';

			// Register menu location
			add_action( 'after_setup_theme', array( $this, 'register_menu_location' ) );

			// Enqueue front-end assets
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

			// Hook into header to render the new nav (dev preview or live)
			add_action( 'wp_head', array( $this, 'output_inline_toggle_css' ) );

			// Initialize native menu item fields, icon picker, and export/import.
			new Atx_Nav_Menu_Admin_Fields();
			new Atx_Nav_Menu_Icons();
			new Atx_Nav_Menu_Export();
			new Atx_Nav_Visual_Builder();
		}

		/**
		 * Register the primary-v2 menu location
		 */
		public function register_menu_location() {
			if ( ! self::is_enabled() ) {
				return;
			}

			register_nav_menus( array(
				Atx_Nav_Menu_Config::get( 'menu_location' ) => __( Atx_Nav_Menu_Config::get( 'menu_location_label' ), 'atx_theme' ),
			) );
		}

		/**
		 * Check if the new nav should be displayed
		 * - Always true if no 'primary' menu exists (fresh install)
		 * - True if ?nav=v2 query param is set (dev preview)
		 * - True if the theme mod 'atx_nav_v2_live' is enabled (go-live switch)
		 *
		 * @return bool
		 */
		public static function is_active() {
			if ( ! self::is_enabled() ) {
				return false;
			}

			// Dev preview mode via query param
			$param = Atx_Nav_Menu_Config::get( 'preview_param' );
			$value = Atx_Nav_Menu_Config::get( 'preview_value' );
			if ( isset( $_GET[ $param ] ) && $_GET[ $param ] === $value ) {
				return true;
			}

			// Go-live toggle (set this in Customizer or via WP-CLI when ready)
			if ( get_theme_mod( 'atx_nav_v2_live', false ) ) {
				return true;
			}

			return false;
		}

		/**
		 * Enqueue CSS and JS for the mega nav
		 */
		public function enqueue_assets() {
			if ( ! self::is_enabled() || ( ! self::is_active() && ! is_customize_preview() ) ) {
				return;
			}

			// Google Fonts
			wp_enqueue_style(
				'google-fonts-inter',
				Atx_Nav_Menu_Config::get( 'google_fonts_url' ),
				array(),
				null
			);

			// Swiper CDN
			$swiper_ver = Atx_Nav_Menu_Config::get( 'swiper_version' );
			wp_enqueue_style( 'swiper', Atx_Nav_Menu_Config::get( 'swiper_css_url' ), array(), $swiper_ver );
			wp_enqueue_script( 'swiper', Atx_Nav_Menu_Config::get( 'swiper_js_url' ), array(), $swiper_ver, true );

			// Frontend CSS modules
			$css_files = array( 'topbar', 'cta', 'dropdown', 'slider', 'brands', 'nested', 'flyout', 'utilities' );
			$prev_css = 'swiper';
			foreach ( $css_files as $file ) {
				$handle = 'atx-nav-' . $file;
				wp_enqueue_style( $handle, self::$module_url . '/assets/css/frontend/' . $file . '.css', array( $prev_css ), self::VERSION );
				$prev_css = $handle;
			}

			// Frontend JS modules
			$js_files = array(
				'core'     => array( 'jquery', 'swiper' ),
				'toplevel' => array( 'atx-nav-js-core' ),
				'nested'   => array( 'atx-nav-js-core' ),
				'flyout'   => array( 'atx-nav-js-core' ),
				'sliders'  => array( 'atx-nav-js-core', 'swiper' ),
				'keyboard' => array( 'atx-nav-js-core' ),
			);
			foreach ( $js_files as $file => $deps ) {
				wp_enqueue_script( 'atx-nav-js-' . $file, self::$module_url . '/assets/js/frontend/' . $file . '.js', $deps, self::VERSION, true );
			}

			wp_localize_script( 'atx-nav-js-core', 'atxNavMenu', array(
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'sliderInterval'  => Atx_Nav_Menu_Config::get( 'slider_autoplay' ),
				'hoverDelay'      => Atx_Nav_Menu_Config::get( 'hover_delay' ),
				'hoverOutDelay'   => Atx_Nav_Menu_Config::get( 'hover_out_delay' ),
				'nestedHoverDelay' => Atx_Nav_Menu_Config::get( 'nested_hover_delay' ),
			) );
		}

		/**
		 * Output inline CSS to hide/show the correct navigation
		 * based on whether v2 is active
		 */
		public function output_inline_toggle_css() {
			if ( ! self::is_enabled() ) {
				echo '<style id="atx-nav-menu-toggle">#atx-nav-menu { display: none !important; }</style>';
				return;
			}

			if ( self::is_active() ) {
				echo '<style id="atx-nav-menu-toggle">
					#main-navigation { display: none !important; }
					#atx-nav-menu { display: block !important; }
				</style>';
			} else {
				echo '<style id="atx-nav-menu-toggle">
					#atx-nav-menu { display: none !important; }
				</style>';
			}
		}

		/**
		 * Get the module path
		 */
		public static function get_module_path() {
			return self::$module_path;
		}

		/**
		 * Get the module URL
		 */
		public static function get_module_url() {
			return self::$module_url;
		}

		/**
		 * Get a template from the templates directory
		 *
		 * @param string $template_name Template file name (without .php)
		 * @param array  $args          Variables to pass to the template
		 * @param bool   $echo          Whether to echo or return
		 * @return string|void
		 */
		public static function get_template( $template_name, $args = array(), $echo = true ) {
			$template_path = self::$module_path . '/templates/' . $template_name . '.php';

			if ( ! file_exists( $template_path ) ) {
				return '';
			}

			if ( ! empty( $args ) ) {
				extract( $args );
			}

			ob_start();
			include $template_path;
			$output = ob_get_clean();

			if ( $echo ) {
				echo $output;
			}

			return $output;
		}
	}

	// Auto-init on require
	Atx_Nav_Menu::instance();
}
