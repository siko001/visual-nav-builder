<?php
/**
 * Atx Nav Menu - Custom Walker
 *
 * Renders the mega menu dropdown structure with:
 * - Category icons and headers
 * - Sub-page links
 * - Column-based layout
 * - Slider and brand integration points
 *
 * @package Atx
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Atx_Nav_Menu_Walker' ) ) {

	class Atx_Nav_Menu_Walker extends Walker_Nav_Menu {

		/**
		 * Track top-level items for mega menu rendering
		 */
		private $current_top_item = null;

		/**
		 * Track current depth-1 (category) items
		 */
		private $current_category = null;

		/**
		 * Whether the current top-level item has the 'atx-nested-nav' class
		 * (for Built-in Appliances style nested navigation)
		 */
		private $is_nested_nav = false;

		/**
		 * Whether the current top-level item has the 'atx-no-extras' class
		 * (to hide slider and brands, like Small Appliances)
		 */
		private $has_no_extras = false;

		/**
		 * Column class for the current dropdown (e.g. 'atx-nav-cols-2')
		 */
		private $cols_class = '';

		/**
		 * Column class for nested tab panels
		 */
		private $nested_cols_class = '';

		/**
		 * Whether the current top-level item has the 'atx-flyout' class
		 */
		private $is_flyout = false;

		/**
		 * Whether the current nested tab has 'atx-flyout' class
		 * (flyout layout inside a secondary nav panel)
		 */
		private $is_nested_flyout = false;

		/**
		 * Track depth-2 item for nested flyout brand cascade
		 */
		private $current_nested_flyout_cat = null;

		/**
		 * Start Level - Opening tag for submenus
		 */
		public function start_lvl( &$output, $depth = 0, $args = null ) {
			if ( $depth === 0 ) {
				if ( $this->is_flyout ) {
					$parent_title = $this->current_top_item ? esc_html( str_replace( '|', ' ', $this->current_top_item->title ) ) : '';

					$output .= <<<HTML
					<div class="atx-nav-mega-dropdown atx-nav-mega-dropdown--flyout">
					<div class="atx-nav-mega-dropdown__inner">
					<div class="atx-nav-flyout">
					<div class="atx-nav-flyout__sidebar">
						<h3 class="atx-nav-flyout__heading">{$parent_title}</h3>
						<ul class="atx-nav-flyout__cat-list">
					HTML;
				} else {
					$extra_class = $this->is_nested_nav ? ' atx-nav-mega-dropdown--nested' : '';
					$extra_class .= $this->has_no_extras ? ' atx-nav-mega-dropdown--no-extras' : '';
					$extra_class .= $this->cols_class;

					$output .= <<<HTML
					<div class="atx-nav-mega-dropdown{$extra_class}">
					<div class="atx-nav-mega-dropdown__inner">
					HTML;

					if ( $this->is_nested_nav ) {
						$parent_title = $this->current_top_item ? esc_html( str_replace( '|', ' ', $this->current_top_item->title ) ) : '';
						$label_chevron = self::chevron_svg( 'atx-nav-nested-label-chevron', 'right' );

						$output .= <<<HTML
						<div class="atx-nav-nested-bar">
						<ul class="atx-nav-nested-bar__list">
							<li class="atx-nav-nested-bar__item atx-nav-nested-bar__item--back">
								<a href="#" class="atx-nav-nested-bar__link">
									<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none">
										<path d="M8.00065 12.6666L3.33398 7.99998L8.00065 3.33331" stroke="#A5A5A5" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/>
										<path d="M12.6673 8H3.33398" stroke="#A5A5A5" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/>
									</svg> All Categories
								</a>
							</li>
							<li class="atx-nav-nested-bar__item atx-nav-nested-bar__item--label">
								<span class="atx-nav-nested-bar__link">{$parent_title}{$label_chevron}</span>
							</li>
						HTML;
					} else {
						$output .= <<<HTML
						<div class="atx-nav-mega-columns">
						<ul class="atx-nav-mega-categories">
						HTML;
					}
				}

			} elseif ( $depth === 1 ) {
				if ( $this->is_flyout ) {
					// Flyout: depth-1 sub-links panel (flex row: sublinks col + brands col)
					$cat_title = $this->current_category ? esc_html( str_replace( '|', ' ', $this->current_category->title ) ) : '';

					$output .= <<<HTML
					<div class="atx-nav-flyout__panel">
					<div class="atx-nav-flyout__panel-main">
					<h4 class="atx-nav-flyout__panel-title">{$cat_title}</h4>
					<ul class="atx-nav-flyout__sublinks">
					HTML;
				} elseif ( $this->is_nested_nav && $this->is_nested_flyout ) {
					// Nested flyout: render flyout sidebar inside nested panel
					$tab_title = $this->current_category ? esc_html( str_replace( '|', ' ', $this->current_category->title ) ) : '';

					$output .= <<<HTML
					<div class="atx-nav-nested-panel atx-nav-nested-panel--flyout">
					<div class="atx-nav-flyout">
					<div class="atx-nav-flyout__sidebar">
						<h3 class="atx-nav-flyout__heading">{$tab_title}</h3>
						<ul class="atx-nav-flyout__cat-list">
					HTML;
				} elseif ( $this->is_nested_nav ) {
					$nested_cols = $this->nested_cols_class;

					$output .= <<<HTML
					<div class="atx-nav-nested-panel{$nested_cols}">
					<div class="atx-nav-nested-panel__row">
					<div class="atx-nav-mega-columns">
					<ul class="atx-nav-mega-categories">
					HTML;
				} else {
					$output .= "\n<ul class=\"atx-nav-mega-sublinks\">\n";
				}

			} elseif ( $depth === 2 && $this->is_nested_nav ) {
				if ( $this->is_nested_flyout ) {
					// Nested flyout: depth-2 sub-links panel
					$cat_title = $this->current_nested_flyout_cat ? esc_html( str_replace( '|', ' ', $this->current_nested_flyout_cat->title ) ) : '';

					$output .= <<<HTML
					<div class="atx-nav-flyout__panel">
					<div class="atx-nav-flyout__panel-main">
					<h4 class="atx-nav-flyout__panel-title">{$cat_title}</h4>
					<ul class="atx-nav-flyout__sublinks">
					HTML;
				} else {
					$output .= "\n<ul class=\"atx-nav-mega-sublinks\">\n";
				}
			}
		}

		/**
		 * End Level - Closing tag for submenus
		 */
		public function end_lvl( &$output, $depth = 0, $args = null ) {
			if ( $depth === 0 ) {
				if ( $this->is_flyout ) {
					// Close sidebar cat-list + sidebar
					$output .= "</ul>\n</div><!-- .atx-nav-flyout__sidebar -->\n";

					// Default area: brands column + slider column (shown when no category hovered)
					$output .= '<div class="atx-nav-flyout__default">';
					if ( $this->current_top_item ) {
						$output .= '<div class="atx-nav-flyout__default-brands">';
						$output .= Atx_Nav_Menu::get_template( 'brands', array( 'item_id' => $this->current_top_item->ID ), false );
						$output .= '</div>';
						$output .= '<div class="atx-nav-flyout__default-slider">';
						$output .= Atx_Nav_Menu::get_template( 'slider', array( 'item_id' => $this->current_top_item->ID ), false );
						$output .= '</div>';
					}
					$output .= "</div><!-- .atx-nav-flyout__default -->\n";

					// Close flyout, inner, dropdown
					$output .= "</div><!-- .atx-nav-flyout -->\n";
					$output .= "</div><!-- .atx-nav-mega-dropdown__inner -->\n";
					$output .= "</div><!-- .atx-nav-mega-dropdown -->\n";

				} else {
					if ( $this->is_nested_nav ) {
						$output .= <<<HTML
							</ul>
						</div><!-- .atx-nav-nested-bar -->
						HTML;
					} else {
						$output .= <<<HTML
							</ul>
						</div><!-- .atx-nav-mega-columns -->
						HTML;
					}

					// Slider (sits as column 3 inside the flex row)
					if ( ! $this->has_no_extras && $this->current_top_item ) {
						$output .= Atx_Nav_Menu::get_template( 'slider', array( 'item_id' => $this->current_top_item->ID ), false );
					}

					$output .= "</div><!-- .atx-nav-mega-dropdown__inner -->\n";

					// Brands (full width, below the flex row)
					if ( ! $this->has_no_extras && $this->current_top_item ) {
						$output .= Atx_Nav_Menu::get_template( 'brands', array( 'item_id' => $this->current_top_item->ID ), false );
					}

					$output .= "</div><!-- .atx-nav-mega-dropdown -->\n";
				}

			} elseif ( $depth === 1 ) {
				if ( $this->is_flyout ) {
					// Close sub-links list + main column
					$output .= "</ul>\n</div><!-- .atx-nav-flyout__panel-main -->\n";

					// Brands column (right side of panel)
					if ( $this->current_category ) {
						$output .= '<div class="atx-nav-flyout__panel-brands">';
						$output .= Atx_Nav_Menu::get_template( 'brands', array( 'item_id' => $this->current_category->ID ), false );
						$output .= '</div>';
					}

					$output .= "</div><!-- .atx-nav-flyout__panel -->\n";

				} elseif ( $this->is_nested_nav && $this->is_nested_flyout ) {
					// Close nested flyout: sidebar, default area, flyout wrapper, panel
					$output .= "</ul>\n</div><!-- .atx-nav-flyout__sidebar -->\n";

					// Default area: tab-level brands + slider
					$output .= '<div class="atx-nav-flyout__default">';
					if ( $this->current_category ) {
						$output .= '<div class="atx-nav-flyout__default-brands">';
						$output .= Atx_Nav_Menu::get_template( 'brands', array( 'item_id' => $this->current_category->ID ), false );
						$output .= '</div>';
						$output .= '<div class="atx-nav-flyout__default-slider">';
						$output .= Atx_Nav_Menu::get_template( 'slider', array( 'item_id' => $this->current_category->ID ), false );
						$output .= '</div>';
					}
					$output .= "</div><!-- .atx-nav-flyout__default -->\n";

					$output .= "</div><!-- .atx-nav-flyout -->\n";
					$output .= "</div><!-- .atx-nav-nested-panel -->\n";

				} elseif ( $this->is_nested_nav ) {
					$output .= <<<HTML
						</ul>
					</div><!-- .atx-nav-mega-columns -->
					HTML;

					// Slider (column 3, inside the flex row)
					if ( ! $this->has_no_extras && $this->current_category ) {
						$output .= Atx_Nav_Menu::get_template( 'slider', array( 'item_id' => $this->current_category->ID ), false );
					}

					$output .= "</div><!-- .atx-nav-nested-panel__row -->\n";

					// Brands (full width, below the row)
					if ( ! $this->has_no_extras && $this->current_category ) {
						$output .= Atx_Nav_Menu::get_template( 'brands', array( 'item_id' => $this->current_category->ID ), false );
					}

					$output .= "</div><!-- .atx-nav-nested-panel -->\n";
				} else {
					$output .= "</ul><!-- .atx-nav-mega-sublinks -->\n";
				}

			} elseif ( $depth === 2 && $this->is_nested_nav ) {
				if ( $this->is_nested_flyout ) {
					// Close nested flyout sub-links + panel with brands
					$output .= "</ul>\n</div><!-- .atx-nav-flyout__panel-main -->\n";

					if ( $this->current_nested_flyout_cat ) {
						$output .= '<div class="atx-nav-flyout__panel-brands">';
						$output .= Atx_Nav_Menu::get_template( 'brands', array( 'item_id' => $this->current_nested_flyout_cat->ID ), false );
						$output .= '</div>';
					}

					$output .= "</div><!-- .atx-nav-flyout__panel -->\n";
				} else {
					$output .= "</ul><!-- .atx-nav-mega-sublinks -->\n";
				}
			}
		}

		/**
		 * Start Element - Individual menu item
		 */
		public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
			$classes = empty( $item->classes ) ? array() : (array) $item->classes;

			// Skip placeholder items (used only to trigger has-children for chevron)
			if ( in_array( 'atx-placeholder', $classes ) ) {
				return;
			}

			$has_children = in_array( 'menu-item-has-children', $classes );

			if ( $depth === 0 ) {
				// Top-level nav item
				$this->current_top_item = $item;
				$this->is_nested_nav    = in_array( 'atx-nested-nav', $classes );
				$this->is_flyout        = in_array( 'atx-flyout', $classes );
				$this->has_no_extras    = in_array( 'atx-no-extras', $classes );

				// Detect column count class (atx-cols-2, atx-cols-3, etc.)
				$this->cols_class = '';
				foreach ( $classes as $class ) {
					if ( preg_match( '/^atx-cols-\d+$/', $class ) ) {
						$this->cols_class = ' ' . $class;
						break;
					}
				}

				$li_classes = array( 'atx-nav-top-item' );
				if ( $has_children ) {
					$li_classes[] = 'atx-nav-top-item--has-children';
				}
				if ( $this->is_nested_nav ) {
					$li_classes[] = 'atx-nav-top-item--nested';
				}
				if ( $this->is_flyout ) {
					$li_classes[] = 'atx-nav-top-item--flyout';
				}
				if ( in_array( 'atx-nav-cta', $classes ) ) {
					$li_classes[] = 'atx-nav-top-item--cta';
				}

				$icon_html  = Atx_Nav_Menu_Icons::get_icon_html( $item->ID );
				$li_class   = esc_attr( implode( ' ', $li_classes ) );
				$id_attr    = esc_attr( $item->ID );
				$url        = esc_url( $item->url );
				$title      = self::render_title( $item->title );
				$chevron    = $has_children ? self::chevron_svg( 'atx-nav-top-arrow' ) : '';

				$output .= <<<HTML
				<li class="{$li_class}" data-item-id="{$id_attr}">
				<a href="{$url}" class="atx-nav-top-link">{$icon_html}<span class="atx-nav-top-label">{$title}</span>{$chevron}</a>
				HTML;

			} elseif ( $depth === 1 ) {
				if ( $this->is_flyout ) {
					// Flyout: depth-1 items are sidebar categories
					$this->current_category = $item;

					$icon_html = Atx_Nav_Menu_Icons::get_icon_html( $item->ID );
					if ( empty( $icon_html ) ) {
						$icon_html = '<span class="atx-nav-flyout__cat-icon"></span>';
					}

					$cat_classes = array( 'atx-nav-flyout__cat' );
					if ( $has_children ) {
						$cat_classes[] = 'atx-nav-flyout__cat--has-panel';
					}

					$cat_class = esc_attr( implode( ' ', $cat_classes ) );
					$id_attr   = esc_attr( $item->ID );
					$url       = esc_url( $item->url );
					$title     = self::render_title( $item->title );
					$chevron   = $has_children ? self::chevron_svg( 'atx-nav-flyout__cat-chevron', 'right' ) : '';

					$output .= <<<HTML
					<li class="{$cat_class}" data-item-id="{$id_attr}">
					<a href="{$url}" class="atx-nav-flyout__cat-link">{$icon_html}<span class="atx-nav-flyout__cat-title">{$title}</span>{$chevron}</a>
					HTML;

				} elseif ( $this->is_nested_nav ) {
					// Nested nav: depth-1 items are the secondary nav tabs
					$this->current_category = $item;
					$this->is_nested_flyout = in_array( 'atx-flyout', $classes );

					// Detect column class on tab item
					$this->nested_cols_class = '';
					foreach ( $classes as $class ) {
						if ( preg_match( '/^atx-cols-\d+$/', $class ) ) {
							$this->nested_cols_class = ' ' . $class;
							break;
						}
					}

					$tab_classes = array( 'atx-nav-nested-bar__item' );
					if ( $has_children ) {
						$tab_classes[] = 'atx-nav-nested-bar__item--has-panel';
					}

					$tab_class = esc_attr( implode( ' ', $tab_classes ) );
					$id_attr   = esc_attr( $item->ID );
					$url       = esc_url( $item->url );
					$title     = esc_html( $item->title );
					$chevron   = $has_children ? self::chevron_svg( 'atx-nav-nested-chevron' ) : '';

					$output .= <<<HTML
					<li class="{$tab_class}" data-item-id="{$id_attr}">
					<a href="{$url}" class="atx-nav-nested-bar__link">{$title}{$chevron}</a>
					HTML;

				} else {
					// Standard mega: depth-1 items are category headers
					$this->current_category = $item;

					$icon_html = Atx_Nav_Menu_Icons::get_icon_html( $item->ID );
					if ( empty( $icon_html ) ) {
						$icon_html = '<span class="atx-nav-mega-category__icon"></span> ';
					}

					$cat_classes = array( 'atx-nav-mega-category' );
					if ( $has_children ) {
						$cat_classes[] = 'atx-nav-mega-category--has-children';
					}
					if ( in_array( 'atx-col-break', $classes ) ) {
						$cat_classes[] = 'atx-col-break';
					}

					$cat_class = esc_attr( implode( ' ', $cat_classes ) );
					$id_attr   = esc_attr( $item->ID );
					$url       = esc_url( $item->url );
					$title     = self::render_title( $item->title );
					$chevron   = $has_children ? self::chevron_svg( 'atx-nav-mega-category__chevron', 'right' ) : '';

					$output .= <<<HTML
					<li class="{$cat_class}" data-item-id="{$id_attr}">
					<a href="{$url}" class="atx-nav-mega-category__link">{$icon_html}<span class="atx-nav-mega-category__title">{$title}</span>{$chevron}</a>
					HTML;
				}

			} elseif ( $depth === 2 ) {
				if ( $this->is_flyout ) {
					// Flyout: depth-2 items are sub-links in the panel
					$id_attr = esc_attr( $item->ID );
					$url     = esc_url( $item->url );
					$title   = esc_html( $item->title );

					// Check if this sub-link has its own brands
					$sublink_brands = Atx_Nav_Menu::get_template( 'brands', array( 'item_id' => $item->ID ), false );
					$chevron = self::chevron_svg( 'atx-nav-flyout__sublink-chevron', 'right' );

					$output .= <<<HTML
					<li class="atx-nav-flyout__sublink" data-item-id="{$id_attr}">
					<a href="{$url}" class="atx-nav-flyout__sublink-link">{$title}{$chevron}</a>
					HTML;

					// Render sub-link brands (hidden by default, JS swaps on hover)
					if ( $sublink_brands ) {
						$output .= '<div class="atx-nav-flyout__sublink-brands">' . $sublink_brands . '</div>';
					}

				} elseif ( $this->is_nested_nav && $this->is_nested_flyout ) {
					// Nested flyout: depth-2 items are flyout sidebar categories
					$this->current_nested_flyout_cat = $item;

					$icon_html = Atx_Nav_Menu_Icons::get_icon_html( $item->ID );
					if ( empty( $icon_html ) ) {
						$icon_html = '<span class="atx-nav-flyout__cat-icon"></span>';
					}

					$cat_classes = array( 'atx-nav-flyout__cat' );
					if ( $has_children ) {
						$cat_classes[] = 'atx-nav-flyout__cat--has-panel';
					}

					$cat_class = esc_attr( implode( ' ', $cat_classes ) );
					$id_attr   = esc_attr( $item->ID );
					$url       = esc_url( $item->url );
					$title     = self::render_title( $item->title );
					$chevron   = $has_children ? self::chevron_svg( 'atx-nav-flyout__cat-chevron', 'right' ) : '';

					$output .= <<<HTML
					<li class="{$cat_class}" data-item-id="{$id_attr}">
					<a href="{$url}" class="atx-nav-flyout__cat-link">{$icon_html}<span class="atx-nav-flyout__cat-title">{$title}</span>{$chevron}</a>
					HTML;

				} elseif ( $this->is_nested_nav ) {
					// Nested nav: depth-2 items are category headers within a tab panel
					$icon_html = Atx_Nav_Menu_Icons::get_icon_html( $item->ID );
					if ( empty( $icon_html ) ) {
						$icon_html = '<span class="atx-nav-mega-category__icon"></span> ';
					}

					$cat_classes = array( 'atx-nav-mega-category' );
					if ( $has_children ) {
						$cat_classes[] = 'atx-nav-mega-category--has-children';
					}

					$cat_class = esc_attr( implode( ' ', $cat_classes ) );
					$id_attr   = esc_attr( $item->ID );
					$url       = esc_url( $item->url );
					$title     = self::render_title( $item->title );
					$chevron   = $has_children ? self::chevron_svg( 'atx-nav-mega-category__chevron', 'right' ) : '';

					$output .= <<<HTML
					<li class="{$cat_class}" data-item-id="{$id_attr}">
						<a href="{$url}" class="atx-nav-mega-category__link">
							{$icon_html}
							<span class="atx-nav-mega-category__title">
								{$title}
							</span>
							{$chevron}
						</a>
					HTML;

				} else {
					// Standard mega: depth-2 items are sub-links
					$id_attr = esc_attr( $item->ID );
					$url     = esc_url( $item->url );
					$title   = esc_html( $item->title );

					$output .= <<<HTML
					<li class="atx-nav-mega-sublink" data-item-id="{$id_attr}">
						<a href="{$url}" class="atx-nav-mega-sublink__link">
							{$title}
						</a>
					HTML;
				}

			} elseif ( $depth === 3 && $this->is_nested_nav && $this->is_nested_flyout ) {
				// Nested flyout: depth-3 items are sub-links in the flyout panel
				$id_attr = esc_attr( $item->ID );
				$url     = esc_url( $item->url );
				$title   = esc_html( $item->title );

				$sublink_brands = Atx_Nav_Menu::get_template( 'brands', array( 'item_id' => $item->ID ), false );
				$chevron = self::chevron_svg( 'atx-nav-flyout__sublink-chevron', 'right' );

				$output .= <<<HTML
				<li class="atx-nav-flyout__sublink" data-item-id="{$id_attr}">
				<a href="{$url}" class="atx-nav-flyout__sublink-link">{$title}{$chevron}</a>
				HTML;

				if ( $sublink_brands ) {
					$output .= '<div class="atx-nav-flyout__sublink-brands">' . $sublink_brands . '</div>';
				}

			} elseif ( $depth === 3 && $this->is_nested_nav ) {
				// Nested nav: depth-3 items are sub-links within a category in a tab panel
				$id_attr = esc_attr( $item->ID );
				$url     = esc_url( $item->url );
				$title   = esc_html( $item->title );

				$output .= <<<HTML
				<li class="atx-nav-mega-sublink" data-item-id="{$id_attr}">
					<a href="{$url}" class="atx-nav-mega-sublink__link">
						{$title}
					</a>
				HTML;
			}
		}

		/**
		 * End Element
		 */
		public function end_el( &$output, $item, $depth = 0, $args = null ) {
			$output .= "</li>\n";
		}

		/**
		 * Render a menu item title with line break support.
		 * Use | in the menu item title to force a line break.
		 * e.g. "Sinks &|Mixers" renders as "Sinks &<br>Mixers"
		 *
		 * @param string $title
		 * @return string
		 */
		private static function render_title( $title ) {
			$parts = explode( '|', $title );
			return implode( '<br>', array_map( 'esc_html', $parts ) );
		}

		/**
		 * Render a dependency-free chevron icon.
		 */
		private static function chevron_svg( $class, $direction = 'down' ) {
			$rotation = $direction === 'right' ? ' atx-nav-chevron--right' : '';
			$class    = esc_attr( 'atx-nav-chevron ' . $class . $rotation );

			return '<svg class="' . $class . '" width="10" height="10" viewBox="0 0 10 10" aria-hidden="true" focusable="false"><path d="M2.25 3.5L5 6.25L7.75 3.5" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>';
		}
	}
}
