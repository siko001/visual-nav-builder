<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div id="atx-vb" class="atx-vb">

	<!-- Header -->
	<div class="atx-vb__header">
		<div style="display:flex;flex-direction:column; justify-content:start;align-items:start; gap:6px;" class="atx-vb__header-left">
			<a href="<?= esc_url( admin_url( 'nav-menus.php' ) ); ?>" class="atx-vb__back">&larr; Back to Menus</a>
			<h1 class="atx-vb__title">ATX Visual Builder</h1>
		</div>
		<div class="atx-vb__header-right">
			<label class="atx-vb__location">
				<span class="screen-reader-text">Menu location</span>
				<select id="atx-vb-menu-location" class="atx-vb__location-select">
					<?php foreach ( $locations as $location => $data ) : ?>
						<?php
						$label = $data['label'] ?? $location;
						if ( ! empty( $data['menu_name'] ) ) {
							$label .= ' - ' . $data['menu_name'];
						}
						?>
						<option value="<?= esc_attr( $location ); ?>" <?= selected( $current_location, $location, false ); ?>>
							<?= esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>
			<label class="atx-vb__menu-name">
				<span>Menu name</span>
				<input type="text" id="atx-vb-menu-name" class="atx-vb__menu-name-input" autocomplete="off" />
			</label>
			<!-- <a href="<?= esc_url( admin_url( 'themes.php?page=atx-nav-export' ) ); ?>" class="button atx-vb__export-btn">Export / Import</a>
			<button type="button" class="button" id="atx-vb-add-root">+ Add Item</button> -->
			<button type="button" class="button button-primary" id="atx-vb-save">Save Changes</button>
		</div>
	</div>

	<!-- Split View -->
	<div class="atx-vb__body">
		<button type="button" class="atx-vb__tree-reopen" id="atx-vb-tree-reopen" title="Open menu tree">☰</button>

		<!-- Left: Tree Editor -->
		<div class="atx-vb__tree-panel">
			<div class="atx-vb__tree-header">
				<div class="atx-vb__tree-toolbar">
					<strong>Menu Tree</strong>
					<button type="button" class="button-link atx-vb__tree-close" id="atx-vb-tree-close" title="Close menu tree">&times;</button>
				</div>
				<input type="text" id="atx-vb-search" placeholder="Search items..." class="atx-vb__search" />
				<div class="atx-vb__button-group">
					<button type="button" class="button button-small" id="atx-vb-add-root" hidden>+ Add Custom Root</button>
					<button type="button" class="button button-small" id="atx-vb-add-existing-toggle" hidden>+ Add Existing Root</button>
					<button type="button" class="button button-small" id="atx-vb-collapse-all" hidden>Collapse All</button>
					<button type="button" class="button button-small" id="atx-vb-expand-all" hidden>Expand All</button>
				</div>
				<div class="atx-vb-existing atx-vb-existing--hidden" id="atx-vb-existing">
					<div class="atx-vb-existing__header">
						<strong>Add Existing Page</strong>
						<button type="button" class="button-link atx-vb-existing__close" id="atx-vb-existing-close" aria-label="Close existing items" title="Close">&times;</button>
					</div>
					<input type="search" id="atx-vb-existing-search" class="widefat" placeholder="Search pages, posts, products, categories..." />
					<div id="atx-vb-existing-results" class="atx-vb-existing__results"></div>
				</div>
			</div>
			<div class="atx-vb__tree" id="atx-vb-tree">
				<p class="atx-vb__tree-loading">Loading...</p>
			</div>
			<div class="atx-vb__tree-resize" id="atx-vb-tree-resize" title="Drag to resize"></div>
		</div>

		<!-- Center: Editor Panel -->
		<div class="atx-vb__editor-panel" id="atx-vb-editor" style="display:none;">
			<div class="atx-vb__editor-header">
				<strong id="atx-vb-editor-title">Edit Item</strong>
				<button type="button" class="atx-vb__editor-close" id="atx-vb-editor-close">&times;</button>
			</div>
			<div class="atx-vb__editor-body">
				<input type="hidden" id="atx-vb-edit-id" />

				<label class="atx-vb__field">
					<span>Title</span>
					<input type="text" id="atx-vb-edit-title" class="widefat" />
				</label>

				<label class="atx-vb__field">
					<span>URL</span>
					<input type="text" id="atx-vb-edit-url" class="widefat" />
				</label>

				<div class="atx-vb__field" id="atx-vb-acf-section">
					<span>ACF Fields</span>
					<div id="atx-vb-acf-fields"></div>
				</div>

				<!-- Classes (tags + dropdown picker only) -->
				<div class="atx-vb__field" id="atx-vb-options-section"></div>

				<!-- CSS Classes (tag pills + dropdown to add) -->
				<div class="atx-vb__field" id="atx-vb-classes-field">
					<span>Classes</span>
					<div id="atx-vb-class-tags" class="atx-vb__tags"></div>
					<select id="atx-vb-class-picker" class="widefat atx-vb__class-picker">
						<option value="">+ Add class...</option>
					</select>
				</div>
				<input type="hidden" id="atx-vb-edit-classes" />

				<!-- Icon -->
				<div class="atx-vb__field">
					<span>Icon</span>
					<div class="atx-vb__icon-controls">
						<select id="atx-vb-edit-icon" class="widefat atx-vb__icon-select">
							<option value="">— No Icon —</option>
							<option value="custom">Upload Custom Icon</option>
						</select>
						<span id="atx-vb-edit-icon-preview" class="atx-vb__icon-preview"></span>
					</div>
					<div id="atx-vb-custom-icon-wrap" class="atx-vb__custom-icon-wrap">
						<input type="hidden" id="atx-vb-edit-icon-custom-id" />
						<div class="atx-vb__custom-icon-controls">
							<div id="atx-vb-custom-icon-preview" class="atx-vb__custom-icon-preview"></div>
							<button type="button" class="button button-small" id="atx-vb-upload-icon">Choose Image</button>
							<button type="button" class="button-link button-small atx-vb__remove-btn" id="atx-vb-remove-icon">Remove</button>
						</div>
					</div>
				</div>

				<hr class="atx-vb__field-divider" />

				<!-- Slider -->
				<div class="atx-vb__field" id="atx-vb-slider-section">
					<div class="atx-vb__field-header">
						<span>Promotional Slider</span>
						<label class="atx-vb__toggle">
							<input type="checkbox" id="atx-vb-slider-enabled" />
							<span class="atx-vb__toggle-track"></span>
						</label>
					</div>
					<div id="atx-vb-slider-items" class="atx-vb__extras-content">
						<div id="atx-vb-slider-list"></div>
						<button type="button" class="button button-small atx-vb__add-action-btn" id="atx-vb-add-slide">+ Add Slide</button>
					</div>
				</div>

				<hr class="atx-vb__field-divider" />

				<!-- Brands -->
				<div class="atx-vb__field" id="atx-vb-brands-section">
					<div class="atx-vb__field-header">
						<span>Brand Logos</span>
						<label class="atx-vb__toggle">
							<input type="checkbox" id="atx-vb-brands-enabled" />
							<span class="atx-vb__toggle-track"></span>
						</label>
					</div>
					<div id="atx-vb-brands-items" class="atx-vb__extras-content">
						<div id="atx-vb-brands-list"></div>
						<button type="button" class="button button-small atx-vb__add-action-btn" id="atx-vb-add-brand">+ Add Brand</button>
					</div>
				</div>

				<hr class="atx-vb__field-divider" />

				<div class="atx-vb__field-row atx-vb__field-row--item-actions">
					<button type="button" class="button" id="atx-vb-add-child">+ Add Custom Page</button>
					<button type="button" class="button" id="atx-vb-add-existing-child">+ Add Existing Page</button>
					<button type="button" class="button atx-vb__delete-btn" id="atx-vb-delete-item">Delete Item</button>
				</div>
			</div>
		</div>

		<!-- Right: Preview -->
		<div class="atx-vb__preview-panel">
			<div class="atx-vb__preview-header">
				<div class="atx-vb__preview-title">
					<span>Live Website Preview</span>
					<span class="atx-vb__preview-context" id="atx-vb-preview-context"></span>
					<div class="atx-vb__viewport-toggle" role="group" aria-label="Preview viewport">
						<button type="button" class="atx-vb__viewport-btn atx-vb__viewport-btn--active" data-viewport="desktop" title="Desktop preview">Desktop</button>
						<button type="button" class="atx-vb__viewport-btn" data-viewport="tablet" title="Tablet preview">Tablet</button>
						<button type="button" class="atx-vb__viewport-btn" data-viewport="mobile" title="Mobile preview">Mobile</button>
					</div>
					<span class="atx-vb__preview-scale" id="atx-vb-preview-scale" title="Preview zoom">100%</span>
				</div>
				<div class="atx-vb__preview-actions">
					<span class="atx-vb__status" id="atx-vb-status"></span>
					<!-- <button type="button" class="button button-small" id="atx-vb-refresh-preview">&#8635; Refresh</button> -->
				</div>
			</div>
			<div class="atx-vb__preview-stage" id="atx-vb-preview-stage">
				<div class="atx-vb__preview-frame" id="atx-vb-preview-frame">
					<iframe id="atx-vb-preview" class="atx-vb__preview-iframe" src="about:blank"></iframe>
				</div>
			</div>
		</div>

	</div>
</div>
<div class="atx-vb-toast-stack" id="atx-vb-toast-stack" aria-live="polite" aria-atomic="true"></div>

<?= Atx_Nav_Menu::get_template( '../visual-builder/templates/help-modal', array(), false ); ?>
