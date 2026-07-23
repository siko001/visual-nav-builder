<?php
/**
 * Admin Template: Slide Row
 *
 * Variables: $item_id, $i, $slide
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$img_url = ! empty( $slide['image'] ) ? wp_get_attachment_image_url( $slide['image'], 'thumbnail' ) : '';
?>
<div class="atx-admin-slide-row">
	<div class="atx-admin-slide-media">
		<input type="hidden" name="atx_slider[<?= esc_attr( $item_id ); ?>][<?= esc_attr( $i ); ?>][image]" value="<?= esc_attr( $slide['image'] ?? '' ); ?>" class="atx-slide-image-id" />
		<div class="atx-admin-slide-preview">
			<?php if ( $img_url ) : ?><img src="<?= esc_url( $img_url ); ?>" /><?php endif; ?>
		</div>
		<button type="button" class="button button-small atx-slide-upload-btn atx-admin-slide-upload-btn">Image</button>
	</div>
	<div class="atx-admin-slide-content">
		<input type="text" name="atx_slider[<?= esc_attr( $item_id ); ?>][<?= esc_attr( $i ); ?>][link]" value="<?= esc_url( $slide['link'] ?? '' ); ?>" placeholder="Link URL" class="widefat" />
		<input type="text" name="atx_slider[<?= esc_attr( $item_id ); ?>][<?= esc_attr( $i ); ?>][badge]" value="<?= esc_attr( $slide['badge'] ?? '' ); ?>" placeholder="Badge (e.g. SPECIAL OFFERS)" class="widefat" />
		<input type="text" name="atx_slider[<?= esc_attr( $item_id ); ?>][<?= esc_attr( $i ); ?>][title]" value="<?= esc_attr( $slide['title'] ?? '' ); ?>" placeholder="Title (e.g. 20% Discount)" class="widefat" />
		<input type="text" name="atx_slider[<?= esc_attr( $item_id ); ?>][<?= esc_attr( $i ); ?>][description]" value="<?= esc_attr( $slide['description'] ?? '' ); ?>" placeholder="Description" class="widefat" />
		<div class="atx-admin-slide-price-row">
			<input type="text" name="atx_slider[<?= esc_attr( $item_id ); ?>][<?= esc_attr( $i ); ?>][original_price]" value="<?= esc_attr( $slide['original_price'] ?? '' ); ?>" placeholder="Was price" class="widefat" />
			<input type="text" name="atx_slider[<?= esc_attr( $item_id ); ?>][<?= esc_attr( $i ); ?>][sale_price]" value="<?= esc_attr( $slide['sale_price'] ?? '' ); ?>" placeholder="Sale price" class="widefat" />
		</div>
	</div>
	<button type="button" class="button-link atx-slide-remove-btn atx-admin-slide-remove-btn">&times;</button>
</div>
