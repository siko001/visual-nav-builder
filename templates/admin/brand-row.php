<?php
/**
 * Admin Template: Brand Row
 *
 * Variables: $item_id, $i, $brand
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$logo_url = ! empty( $brand['logo'] ) ? wp_get_attachment_image_url( $brand['logo'], 'thumbnail' ) : '';
?>
<div class="atx-admin-brand-row">
	<input type="hidden" name="atx_brands[<?= esc_attr( $item_id ); ?>][<?= esc_attr( $i ); ?>][logo]" value="<?= esc_attr( $brand['logo'] ?? '' ); ?>" class="atx-brand-logo-id" />
	<div class="atx-admin-brand-preview">
		<?php if ( $logo_url ) : ?><img src="<?= esc_url( $logo_url ); ?>" /><?php endif; ?>
	</div>
	<input type="text" name="atx_brands[<?= esc_attr( $item_id ); ?>][<?= esc_attr( $i ); ?>][name]" value="<?= esc_attr( $brand['name'] ?? '' ); ?>" placeholder="Brand name" class="widefat atx-admin-brand-input" />
	<input type="text" name="atx_brands[<?= esc_attr( $item_id ); ?>][<?= esc_attr( $i ); ?>][link]" value="<?= esc_url( $brand['link'] ?? '' ); ?>" placeholder="Brand URL" class="widefat atx-admin-brand-input" />
	<button type="button" class="button button-small atx-brand-upload-btn atx-admin-upload-btn">Logo</button>
	<button type="button" class="button-link atx-brand-remove-btn atx-admin-remove-btn">&times;</button>
</div>
