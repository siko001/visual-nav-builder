<?php
/**
 * Admin Template: Slider & Brands Extras Field
 *
 * Variables: $item_id, $slider_enabled, $slider_items, $brands_enabled, $brand_items
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="atx-nav-extras" data-item-id="<?= esc_attr( $item_id ); ?>">

	<!-- Slider -->
	<p class="description description-wide">
		<label>
			<input type="checkbox"
				name="atx_slider_enabled[<?= esc_attr( $item_id ); ?>]"
				value="1"
				class="atx-nav-slider-toggle"
				<?php checked( $slider_enabled, '1' ); ?> />
			<strong><?php esc_html_e( 'Enable Promotional Slider', 'crosscraft-child' ); ?></strong>
		</label>
	</p>

	<div class="atx-nav-slider-config atx-admin-extras-config<?= $slider_enabled ? '' : ' is-hidden'; ?>">
		<div class="atx-nav-slider-items" data-item-id="<?= esc_attr( $item_id ); ?>">
			<?php foreach ( $slider_items as $i => $slide ) :
				echo Atx_Nav_Menu::get_template( 'admin/slide-row', array(
					'item_id' => $item_id,
					'i'       => $i,
					'slide'   => $slide,
				), false );
			endforeach; ?>
		</div>
		<button type="button" class="button button-small atx-add-slide-btn" data-item-id="<?= esc_attr( $item_id ); ?>">+ Add Slide</button>
	</div>

	<!-- Brands -->
	<p class="description description-wide">
		<label>
			<input type="checkbox"
				name="atx_brands_enabled[<?= esc_attr( $item_id ); ?>]"
				value="1"
				class="atx-nav-brands-toggle"
				<?php checked( $brands_enabled, '1' ); ?> />
			<strong><?php esc_html_e( 'Enable Brand Logos', 'crosscraft-child' ); ?></strong>
		</label>
	</p>

	<div class="atx-nav-brands-config atx-admin-extras-config<?= $brands_enabled ? '' : ' is-hidden'; ?>">
		<div class="atx-nav-brand-items" data-item-id="<?= esc_attr( $item_id ); ?>">
			<?php foreach ( $brand_items as $i => $brand ) :
				echo Atx_Nav_Menu::get_template( 'admin/brand-row', array(
					'item_id' => $item_id,
					'i'       => $i,
					'brand'   => $brand,
				), false );
			endforeach; ?>
		</div>
		<button type="button" class="button button-small atx-add-brand-btn" data-item-id="<?= esc_attr( $item_id ); ?>">+ Add Brand</button>
	</div>
</div>
