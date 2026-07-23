<?php
/**
 * Template: Promotional Slider (Swiper)
 *
 * Variables available: $item_id (menu item post ID)
 *
 * @package Atx
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$slider_items = Atx_Nav_Menu_MetaBox::get_slider_items( isset( $item_id ) ? $item_id : 0 );

if ( empty( $slider_items ) ) {
	return;
}
?>

<div class="atx-nav-slider swiper" data-atx-slider>
	<div class="swiper-wrapper">
		<?php foreach ( $slider_items as $index => $slide ) :
			$image_url = '';
			if ( ! empty( $slide['image'] ) ) {
				$image_url = wp_get_attachment_image_url( absint( $slide['image'] ), 'medium_large' );
			}
		?>
			<div class="swiper-slide atx-nav-slider__slide"
				<?php if ( ! empty( $slide['link'] ) ) : ?>data-link="<?= esc_url( $slide['link'] ); ?>"<?php endif; ?>>

				<?php if ( ! empty( $slide['badge'] ) ) : ?>
					<span class="atx-nav-slider__badge"><?= esc_html( $slide['badge'] ); ?></span>
				<?php endif; ?>

				<div class="atx-nav-slider__body">
					<?php if ( $image_url ) : ?>
						<div class="atx-nav-slider__image">
							<img src="<?= esc_url( $image_url ); ?>"
								alt="<?= esc_attr( $slide['title'] ?? '' ); ?>"
								loading="lazy" />
						</div>
					<?php endif; ?>

					<div class="atx-nav-slider__content">
						<?php if ( ! empty( $slide['title'] ) ) : ?>
							<div class="atx-nav-slider__subtitle">
								<?php
								$title_parts = explode( ' ', $slide['title'], 2 );
								?>
								<span class="atx-nav-slider__subtitle-highlight"><?= esc_html( $title_parts[0] ); ?></span>
								<?php if ( ! empty( $title_parts[1] ) ) : ?>
									<span class="atx-nav-slider__subtitle-text"><?= esc_html( $title_parts[1] ); ?></span>
								<?php endif; ?>
							</div>
						<?php endif; ?>

						<?php if ( ! empty( $slide['description'] ) ) : ?>
							<p class="atx-nav-slider__desc"><?= esc_html( $slide['description'] ); ?></p>
						<?php endif; ?>

						<?php if ( ! empty( $slide['original_price'] ) || ! empty( $slide['sale_price'] ) ) : ?>
							<div class="atx-nav-slider__pricing">
								<?php if ( ! empty( $slide['original_price'] ) ) : ?>
									<span class="atx-nav-slider__original-price">Was <?= esc_html( $slide['original_price'] ); ?></span>
								<?php endif; ?>
								<?php if ( ! empty( $slide['sale_price'] ) ) : ?>
									<span class="atx-nav-slider__sale-price"><?= esc_html( $slide['sale_price'] ); ?></span>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
		<?php endforeach; ?>
	</div>

	<?php if ( count( $slider_items ) > 1 ) : ?>
		<div class="swiper-pagination atx-nav-slider__dots"></div>
	<?php endif; ?>
</div>
