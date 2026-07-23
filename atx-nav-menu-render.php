<?php
/**
 * Atx Nav Menu - Render
 *
 * Hooks into the header to render the new mega navigation bar.
 * Supports live preview from transient data (no save required).
 *
 * @package Atx
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Atx_Nav_Menu_Render' ) ) {

	class Atx_Nav_Menu_Render {

		public function __construct() {
			add_action( 'visualcomposerstarter_after_header_menu', array( $this, 'render_nav' ) );
		}

		/**
		 * Render the full mega navigation bar
		 */
		public function render_nav() {
			if ( ! Atx_Nav_Menu::is_enabled() ) {
				return;
			}

			$is_live_preview = isset( $_GET['preview_live'] ) && sanitize_text_field( $_GET['preview_live'] ) === '1';

			if ( ! $is_live_preview && ! has_nav_menu( Atx_Nav_Menu::MENU_LOCATION ) ) {
				return;
			}

			?>
			<div id="atx-nav-menu" class="atx-nav-menu">
				<div class="atx-nav-menu__container">
					<div class="atx-nav-menu__scroll-area">
						<?php
						if ( $is_live_preview ) {
							$this->render_live_preview();
						} else {
							wp_nav_menu( array(
								'theme_location' => Atx_Nav_Menu::MENU_LOCATION,
								'menu_class'     => 'atx-nav-menu__list',
								'container'      => '',
								'items_wrap'     => '<ul id="%1$s" class="%2$s">%3$s</ul>',
								'walker'         => new Atx_Nav_Menu_Walker(),
								'depth'          => 4,
							) );
						}
						?>
					</div>
					<div class="atx-nav-menu__cta-fixed"></div>
				</div>
			</div>
			<?php
		}

		/**
		 * Render from transient data (live preview, no save needed)
		 */
		private function render_live_preview() {
			$items = get_transient( 'atx_nav_live_preview' );
			if ( empty( $items ) ) {
				echo '<ul class="atx-nav-menu__list"><li class="atx-nav-top-item" style="padding:14px;">No preview data. Click Refresh.</li></ul>';
				return;
			}

			// Build fake WP menu item objects
			$menu_items = array();

			foreach ( $items as $item ) {
				$obj = new stdClass();
				$obj->ID               = $item['id'];
				$obj->db_id            = $item['id'];
				$obj->title            = $item['title'];
				$obj->url              = $item['url'] ?: '#';
				$obj->menu_item_parent = $item['parent_id'];
				$obj->menu_order       = $item['position'];
				$obj->classes          = $item['classes'] ?: array();
				$obj->type             = 'custom';
				$obj->object           = 'custom';
				$obj->object_id        = $item['id'];
				$obj->target           = '';
				$obj->attr_title       = '';
				$obj->description      = '';
				$obj->xfn              = '';

				// Check if any child references this item as parent
				$has_children = false;
				foreach ( $items as $check ) {
					if ( $check['parent_id'] == $item['id'] ) {
						$has_children = true;
						break;
					}
				}
				if ( $has_children ) {
					$obj->classes[] = 'menu-item-has-children';
				}

				$menu_items[] = $obj;
			}

			// Use the walker to render
			$walker = new Atx_Nav_Menu_Walker();
			$output = '';

			$args = (object) array(
				'before'      => '',
				'after'       => '',
				'link_before' => '',
				'link_after'  => '',
				'walker'      => $walker,
			);

			$output .= '<ul class="atx-nav-menu__list">';
			$output .= $walker->walk( $menu_items, 4, $args );
			$output .= '</ul>';

			echo $output;
		}
	}

	new Atx_Nav_Menu_Render();
}
