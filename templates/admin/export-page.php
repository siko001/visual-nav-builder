<?php
/**
 * Admin Template: Export / Import Page
 *
 * Variables: $exports (array of export files)
 */
if ( ! defined( 'ABSPATH' ) ) exit;
$current_location = $current_location ?? Atx_Nav_Menu_Export::MENU_LOCATION;
$default_exports  = $default_exports ?? array();
$current_default  = $default_exports[ $current_location ] ?? '';
?>
<div class="wrap">
	<h1>Visual Nav Builder - Export / Import</h1>

	<div class="atx-admin-section">
		<h2>Menu Location</h2>
		<p class="description">Choose which registered menu location to export, import, or reset.</p>
		<select id="atx-export-menu-location" class="regular-text">
			<?php foreach ( get_registered_nav_menus() as $location => $label ) : ?>
				<option value="<?= esc_attr( $location ); ?>" <?= selected( $current_location, $location, false ); ?>>
					<?= esc_html( $label . ' (' . $location . ')' ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</div>

	<!-- Export Section -->
	<div class="atx-admin-section">
		<h2>Export</h2>
		<p class="description">Export the selected menu with all items, supported meta, and images into a portable JSON file.</p>
		<button type="button" class="button button-primary atx-export-btn" id="atx-export-btn">
			Export Menu
		</button>
		<span class="atx-export-status atx-admin-status" id="atx-export-status"></span>
	</div>

	<!-- Import Section -->
	<div class="atx-admin-section">
		<h2>Import</h2>
		<p class="description">Import a previously exported menu. This will <strong>replace</strong> the selected menu location.</p>

		<div class="atx-admin-import-row">
			<div class="atx-admin-import-form">
				<label for="atx-import-file" class="atx-admin-import-label">Upload JSON file</label>
				<input type="file" id="atx-import-file" accept=".json" class="atx-admin-file-input" />
			</div>
			<div class="atx-admin-import-btn-wrap">
				<button type="button" class="button button-primary atx-import-btn" id="atx-import-btn">
					Import Menu
				</button>
			</div>
		</div>
		<span class="atx-import-status atx-admin-status" id="atx-import-status"></span>
	</div>

	<div class="atx-admin-section">
		<h2>Default Export</h2>
		<p class="description">Choose one of the saved exports as the default import for this menu location. The same export can be the default for multiple locations.</p>
		<p>
			Current default:
			<strong id="atx-current-default"><?= $current_default ? esc_html( $current_default ) : 'None set'; ?></strong>
		</p>
		<button type="button" class="button" id="atx-import-default" <?= $current_default ? '' : 'disabled'; ?>>
			Import Default for Selected Location
		</button>
		<span class="atx-admin-status" id="atx-default-status"></span>
	</div>

	<!-- Reset to Default -->
	<?php if ( $current_location === Atx_Nav_Menu_Export::MENU_LOCATION && Atx_Nav_Menu_Export::get_baseline_path() ) : ?>
	<div class="atx-admin-section">
		<h2>Reset to Default</h2>
		<p class="description">Reset the navigation to its original default state. This will <strong>replace</strong> the current menu with the baseline configuration.</p>
		<button type="button" class="button atx-admin-reset-btn" id="atx-reset-baseline">
			Reset to Default
		</button>
		<span class="atx-admin-status" id="atx-reset-status"></span>
	</div>
	<?php endif; ?>

	<!-- Existing Exports -->
	<?php if ( ! empty( $exports ) ) : ?>
	<div class="atx-admin-section">
		<h2>Previous Exports</h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th>File</th>
					<th>Menu</th>
					<th>Default For</th>
					<th>Date</th>
					<th>Size</th>
					<th>Actions</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $exports as $export ) : ?>
				<tr>
					<td><code><?= esc_html( $export['name'] ); ?></code></td>
					<td>
						<?= esc_html( $export['menu_name'] ?: '-' ); ?><br>
						<small><?= esc_html( $export['menu_location'] ?: '' ); ?></small>
					</td>
					<td><?= ! empty( $export['default_for'] ) ? esc_html( implode( ', ', $export['default_for'] ) ) : '-'; ?></td>
					<td><?= esc_html( $export['date'] ); ?></td>
					<td><?= esc_html( $export['size'] ); ?></td>
					<td>
						<a href="<?= esc_url( $export['url'] ); ?>" class="button button-small" download>Download</a>
						<button type="button" class="button button-small atx-import-from-server" data-file="<?= esc_attr( $export['path'] ); ?>">Import This</button>
						<button type="button" class="button button-small atx-set-default-export" data-file="<?= esc_attr( $export['name'] ); ?>">Set Default</button>
						<button type="button" class="button button-small atx-delete-export" data-file="<?= esc_attr( $export['name'] ); ?>" <?= $export['can_delete'] ? '' : 'disabled title="Default exports cannot be deleted"'; ?>>Delete</button>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php endif; ?>

	<!-- Log Output -->
	<div id="atx-export-log" class="atx-admin-export-log"></div>
</div>
