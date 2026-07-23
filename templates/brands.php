<?php
/**
 * Template: Brand Logos
 *
 * Renders the brand logos strip at the bottom of mega dropdowns.
 * Variables available: $item_id (menu item post ID)
 *
 * @package Atx
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$brand_items = Atx_Nav_Menu_MetaBox::get_brand_items( isset( $item_id ) ? $item_id : 0 );

if ( empty( $brand_items ) ) {
	return;
}
?>

<div class="atx-nav-brands">
	<div class="atx-nav-brands__inner">
		<span class="atx-nav-brands__title">Brands</span>
		<div class="atx-nav-brands__list">
		<?php foreach ( $brand_items as $brand ) :
			$logo_url = '';
			if ( ! empty( $brand['logo'] ) ) {
				$logo_url = wp_get_attachment_image_url( absint( $brand['logo'] ), 'full' );
			}

			$brand_name = ! empty( $brand['name'] ) ? $brand['name'] : '';
			$brand_link = ! empty( $brand['link'] ) ? $brand['link'] : '#';
		?>
			<a href="<?= esc_url( $brand_link ); ?>" class="atx-nav-brands__item">
				<?php if ( $logo_url ) : ?>
					<img src="<?= esc_url( $logo_url ); ?>"
						alt="<?= esc_attr( $brand_name ); ?>"
						class="atx-nav-brands__logo"
						loading="lazy" />
				<?php else : ?>
					<span class="atx-nav-brands__name"><?= esc_html( $brand_name ); ?></span>
				<?php endif; ?>
			</a>
		<?php endforeach; ?>
		</div>
	</div>
</div>
