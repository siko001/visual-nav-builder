<?php
/**
 * Admin Template: Icon Picker Field
 *
 * Variables: $item_id, $selected_icon, $custom_icon, $custom_url, $icons
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<p class="field-atx-icon description description-wide">
	<label for="atx-nav-icon-<?= esc_attr( $item_id ); ?>">
		<?php esc_html_e( 'Menu Icon', 'atx_theme' ); ?>
	</label>
	<br/>
	<select id="atx-nav-icon-<?= esc_attr( $item_id ); ?>"
		name="atx_nav_icon[<?= esc_attr( $item_id ); ?>]"
		class="widefat atx-nav-icon-select">
		<option value=""><?php esc_html_e( '— No Icon —', 'atx_theme' ); ?></option>
		<?php foreach ( $icons as $key => $icon ) : ?>
			<option value="<?= esc_attr( $key ); ?>" <?php selected( $selected_icon, $key ); ?>>
				<?= esc_html( $icon['label'] ); ?>
			</option>
		<?php endforeach; ?>
		<option value="custom" <?php selected( $selected_icon, 'custom' ); ?>>
			<?php esc_html_e( 'Upload Custom Icon', 'atx_theme' ); ?>
		</option>
	</select>

	<!-- Icon preview -->
	<span class="atx-nav-icon-preview atx-admin-icon-preview">
		<?php
		if ( $selected_icon && $selected_icon !== 'custom' && isset( $icons[ $selected_icon ] ) ) {
			echo $icons[ $selected_icon ]['svg'];
		} elseif ( $selected_icon === 'custom' && $custom_url ) {
			echo '<img src="' . esc_url( $custom_url ) . '" />';
		}
		?>
	</span>

	<!-- Custom icon upload -->
	<span class="atx-nav-icon-custom-wrap atx-admin-icon-custom-wrap<?= $selected_icon === 'custom' ? ' is-active' : ''; ?>">
		<input type="hidden"
			name="atx_nav_icon_custom[<?= esc_attr( $item_id ); ?>]"
			value="<?= esc_attr( $custom_icon ); ?>"
			class="atx-nav-icon-custom-id" />
		<span class="atx-nav-icon-custom-preview atx-admin-icon-custom-preview">
			<?php if ( $custom_url ) : ?>
				<img src="<?= esc_url( $custom_url ); ?>" />
			<?php endif; ?>
		</span>
		<button type="button" class="button button-small atx-nav-icon-upload"><?php esc_html_e( 'Upload Icon', 'atx_theme' ); ?></button>
		<button type="button" class="button-link button-small atx-nav-icon-remove atx-admin-icon-remove-btn<?= ! $custom_icon ? ' is-hidden' : ''; ?>"><?php esc_html_e( 'Remove', 'atx_theme' ); ?></button>
	</span>
</p>
