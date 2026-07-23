/**
 * Visual Builder - Extras (Slider, Brands, Custom Icon Upload)
 * Manages slider items, brand logos, and custom icon uploads per menu item.
 */
(function ($) {
	'use strict';

	let VB = window.AtxVB;

	// Store extras data per item ID
	VB.extras = VB.extras || {};

	// ── Load extras from server ──
	let origOpenEditor = VB.openEditor;

	VB.openEditor = function (item) {
		// Call original openEditor for title/url/classes/icon
		origOpenEditor.call(this, item);

		if (!this.hasExtension || !this.hasExtension('mega-nav')) {
			$('#atx-vb-slider-section, #atx-vb-brands-section, #atx-vb-custom-icon-wrap').hide();
			return;
		}

		let id = item.id;

		// Show slider & brands on:
		// - Top-level items (depth 0) with children that are NOT nested
		// - Depth-1 items inside nested parents (secondary nav tabs)
		// - Flyout: depth-0 (brands + slider), depth-1 categories (brands only)
		let depth = this.getDepth(id);
		let hasChildren = this.getChildren(id).length > 0;
		let itemClasses = item.classes || [];
		let showExtras = false;
		let showSlider = true;
		let showBrands = true;

		// Check if item is inside a flyout parent
		let isInsideFlyout = false;
		let current = item;
		while (current && current.parent_id) {
			let p = this.getItem(current.parent_id);
			if (p && (p.classes || []).includes('atx-flyout')) {
				isInsideFlyout = true;
				break;
			}
			current = p;
		}

		if (depth === 0 && hasChildren && !itemClasses.includes('atx-nested-nav') && !itemClasses.includes('atx-cols-4')) {
			showExtras = true;
		} else if (depth === 1 && hasChildren) {
			let parent = this.getItem(item.parent_id);
			if (parent && (parent.classes || []).includes('atx-nested-nav')) {
				showExtras = true;
			}
		}

		// Flyout (top-level): depth-0 gets slider + brands, depth-1/2 get brands only
		// Flyout (nested tab): depth-1 with atx-flyout gets slider + brands, depth-2/3 get brands only
		if (depth === 0 && hasChildren && itemClasses.includes('atx-flyout')) {
			showExtras = true;
		} else if (depth === 1 && hasChildren && itemClasses.includes('atx-flyout')) {
			// Nested flyout tab itself — gets both slider + brands
			showExtras = true;
		} else if (isInsideFlyout && !itemClasses.includes('atx-flyout')) {
			// Any descendant inside a flyout — brands only
			showExtras = true;
			showSlider = false;
		}

		$('#atx-vb-slider-section').toggle(showExtras && showSlider);
		$('#atx-vb-brands-section').toggle(showExtras && showBrands);

		VB.extras[id] = VB.extras[id] || item.extras || {};
		item.extras = VB.extras[id];

		// Custom icon
		let isCustom = item.icon === 'custom';
		$('#atx-vb-custom-icon-wrap').toggle(isCustom);
		if (isCustom && VB.extras[id]) {
			renderCustomIcon(VB.extras[id].custom_icon_url || '');
			$('#atx-vb-edit-icon-custom-id').val(VB.extras[id].custom_icon_id || '');
		}

		if (!showExtras) return;
		renderExtras(id);
	};

	function renderExtras(id) {
		let data = VB.extras[id] || {};

		// Slider
		$('#atx-vb-slider-enabled').prop('checked', data.slider_enabled === '1');
		$('#atx-vb-slider-items').toggle(data.slider_enabled === '1');
		renderSliderList(id, data.slider_items || []);

		// Brands
		$('#atx-vb-brands-enabled').prop('checked', data.brands_enabled === '1');
		$('#atx-vb-brands-items').toggle(data.brands_enabled === '1');
		renderBrandsList(id, data.brand_items || []);
	}

	// ── Slider ──

	function renderSliderList(itemId, slides) {
		let $list = $('#atx-vb-slider-list').html('');
		slides.forEach((slide, i) => {
			let imgHtml = slide.image_url
				? `<img src="${slide.image_url}" style="width:100%;height:100%;object-fit:cover;" />`
				: '';
			$list.append(`
				<div class="atx-vb-slide-row" data-index="${i}" style="display:flex;gap:6px;align-items:flex-start;margin-bottom:6px;padding:8px;background:#f9f9f9;border:1px solid #eee;border-radius:4px;font-size:12px;">
					<div style="flex-shrink:0;">
						<div class="atx-vb-slide-img" style="width:50px;height:50px;border:1px solid #ddd;border-radius:4px;overflow:hidden;background:#fff;cursor:pointer;" title="Click to change image">${imgHtml}</div>
						<input type="hidden" class="atx-vb-slide-image-id" value="${slide.image || ''}" />
					</div>
					<div style="flex:1;min-width:0;">
						<input type="text" class="atx-vb-slide-field widefat" data-field="badge" value="${slide.badge || ''}" placeholder="Badge (e.g. SPECIAL OFFERS)" style="margin-bottom:3px;font-size:11px;" />
						<input type="text" class="atx-vb-slide-field widefat" data-field="title" value="${slide.title || ''}" placeholder="Title (e.g. 20% Discount)" style="margin-bottom:3px;font-size:11px;" />
						<input type="text" class="atx-vb-slide-field widefat" data-field="description" value="${slide.description || ''}" placeholder="Description" style="margin-bottom:3px;font-size:11px;" />
						<div style="display:flex;gap:3px;margin-bottom:3px;">
							<input type="text" class="atx-vb-slide-field widefat" data-field="original_price" value="${slide.original_price || ''}" placeholder="Was price" style="font-size:11px;" />
							<input type="text" class="atx-vb-slide-field widefat" data-field="sale_price" value="${slide.sale_price || ''}" placeholder="Sale price" style="font-size:11px;" />
						</div>
						<input type="text" class="atx-vb-slide-field widefat" data-field="link" value="${slide.link || ''}" placeholder="Link URL" style="font-size:11px;" />
					</div>
					<button type="button" class="button-link atx-vb-remove-slide" style="color:#a00;font-size:14px;flex-shrink:0;">&times;</button>
				</div>
			`);
		});
	}

	// Toggle slider
	$(document).on('change', '#atx-vb-slider-enabled', function () {
		let enabled = this.checked;
		$('#atx-vb-slider-items').toggle(enabled);
		let id = parseInt($('#atx-vb-edit-id').val(), 10);
		if (!VB.extras[id]) VB.extras[id] = {};
		VB.extras[id].slider_enabled = enabled ? '1' : '';
		saveExtras(id);
	});

	// Add slide
	$(document).on('click', '#atx-vb-add-slide', function () {
		let id = parseInt($('#atx-vb-edit-id').val(), 10);
		if (!VB.extras[id]) VB.extras[id] = {};
		if (!VB.extras[id].slider_items) VB.extras[id].slider_items = [];
		VB.extras[id].slider_items.push({ image: 0, image_url: '', badge: '', title: '', link: '', description: '', original_price: '', sale_price: '' });
		renderSliderList(id, VB.extras[id].slider_items);
		saveExtras(id);
	});

	// Remove slide
	$(document).on('click', '.atx-vb-remove-slide', function () {
		let $row = $(this).closest('.atx-vb-slide-row');
		let i = $row.data('index');
		let id = parseInt($('#atx-vb-edit-id').val(), 10);
		if (VB.extras[id] && VB.extras[id].slider_items) {
			VB.extras[id].slider_items.splice(i, 1);
			renderSliderList(id, VB.extras[id].slider_items);
			saveExtras(id);
		}
	});

	// Slide image click
	$(document).on('click', '.atx-vb-slide-img', function () {
		let $row = $(this).closest('.atx-vb-slide-row');
		let $img = $(this);
		let $input = $row.find('.atx-vb-slide-image-id');
		let i = $row.data('index');
		let id = parseInt($('#atx-vb-edit-id').val(), 10);

		window.atxOpenImagePicker(function (imgId, imgUrl) {
			$input.val(imgId);
			$img.html(`<img src="${imgUrl}" style="width:100%;height:100%;object-fit:cover;" />`);
			if (VB.extras[id] && VB.extras[id].slider_items && VB.extras[id].slider_items[i]) {
				VB.extras[id].slider_items[i].image = imgId;
				VB.extras[id].slider_items[i].image_url = imgUrl;
				saveExtras(id);
			}
		});
	});

	// Slide field change
	$(document).on('input', '.atx-vb-slide-field', function () {
		let $row = $(this).closest('.atx-vb-slide-row');
		let i = $row.data('index');
		let field = $(this).data('field');
		let id = parseInt($('#atx-vb-edit-id').val(), 10);
		if (VB.extras[id] && VB.extras[id].slider_items && VB.extras[id].slider_items[i]) {
			VB.extras[id].slider_items[i][field] = $(this).val();
			clearTimeout(VB._extrasSaveTimer);
			VB._extrasSaveTimer = setTimeout(() => saveExtras(id), 600);
		}
	});

	// ── Brands ──

	function renderBrandsList(itemId, brands) {
		let $list = $('#atx-vb-brands-list').html('');
		brands.forEach((brand, i) => {
			let logoHtml = brand.logo_url
				? `<img src="${brand.logo_url}" style="width:100%;height:100%;object-fit:contain;" />`
				: '';
			$list.append(`
				<div class="atx-vb-brand-row" data-index="${i}" style="display:flex;gap:8px;align-items:flex-start;margin-bottom:6px;padding:8px;background:#f9f9f9;border:1px solid #eee;border-radius:4px;font-size:12px;">
					<div class="atx-vb-brand-logo" style="width:40px;height:40px;border:1px solid #ddd;border-radius:4px;overflow:hidden;background:#fff;cursor:pointer;flex-shrink:0;" title="Click to change logo">${logoHtml}</div>
					<input type="hidden" class="atx-vb-brand-logo-id" value="${brand.logo || ''}" />
					<div style="flex:1;min-width:0;">
						<input type="text" class="atx-vb-brand-field widefat" data-field="name" value="${brand.name || ''}" placeholder="Brand name" style="margin-bottom:3px;font-size:11px;" />
						<input type="text" class="atx-vb-brand-field widefat" data-field="link" value="${brand.link || ''}" placeholder="Brand URL" style="font-size:11px;" />
					</div>
					<button type="button" class="button-link atx-vb-remove-brand" style="color:#a00;font-size:14px;flex-shrink:0;">&times;</button>
				</div>
			`);
		});
	}

	// Toggle brands
	$(document).on('change', '#atx-vb-brands-enabled', function () {
		let enabled = this.checked;
		$('#atx-vb-brands-items').toggle(enabled);
		let id = parseInt($('#atx-vb-edit-id').val(), 10);
		if (!VB.extras[id]) VB.extras[id] = {};
		VB.extras[id].brands_enabled = enabled ? '1' : '';
		saveExtras(id);
	});

	// Add brand
	$(document).on('click', '#atx-vb-add-brand', function () {
		let id = parseInt($('#atx-vb-edit-id').val(), 10);
		if (!VB.extras[id]) VB.extras[id] = {};
		if (!VB.extras[id].brand_items) VB.extras[id].brand_items = [];
		VB.extras[id].brand_items.push({ logo: 0, logo_url: '', name: '', link: '' });
		renderBrandsList(id, VB.extras[id].brand_items);
		saveExtras(id);
	});

	// Remove brand
	$(document).on('click', '.atx-vb-remove-brand', function () {
		let $row = $(this).closest('.atx-vb-brand-row');
		let i = $row.data('index');
		let id = parseInt($('#atx-vb-edit-id').val(), 10);
		if (VB.extras[id] && VB.extras[id].brand_items) {
			VB.extras[id].brand_items.splice(i, 1);
			renderBrandsList(id, VB.extras[id].brand_items);
			saveExtras(id);
		}
	});

	// Brand logo click
	$(document).on('click', '.atx-vb-brand-logo', function () {
		let $row = $(this).closest('.atx-vb-brand-row');
		let $logo = $(this);
		let $input = $row.find('.atx-vb-brand-logo-id');
		let i = $row.data('index');
		let id = parseInt($('#atx-vb-edit-id').val(), 10);

		window.atxOpenImagePicker(function (imgId, imgUrl) {
			$input.val(imgId);
			$logo.html(`<img src="${imgUrl}" style="width:100%;height:100%;object-fit:contain;" />`);
			if (VB.extras[id] && VB.extras[id].brand_items && VB.extras[id].brand_items[i]) {
				VB.extras[id].brand_items[i].logo = imgId;
				VB.extras[id].brand_items[i].logo_url = imgUrl;
				saveExtras(id);
			}
		});
	});

	// Brand field change
	$(document).on('input', '.atx-vb-brand-field', function () {
		let $row = $(this).closest('.atx-vb-brand-row');
		let i = $row.data('index');
		let field = $(this).data('field');
		let id = parseInt($('#atx-vb-edit-id').val(), 10);
		if (VB.extras[id] && VB.extras[id].brand_items && VB.extras[id].brand_items[i]) {
			VB.extras[id].brand_items[i][field] = $(this).val();
			clearTimeout(VB._extrasSaveTimer);
			VB._extrasSaveTimer = setTimeout(() => saveExtras(id), 600);
		}
	});

	// ── Custom Icon Upload ──

	$(document).on('change', '#atx-vb-edit-icon', function () {
		let isCustom = $(this).val() === 'custom';
		$('#atx-vb-custom-icon-wrap').toggle(isCustom);
	});

	$(document).on('click', '#atx-vb-upload-icon', function () {
		window.atxOpenImagePicker(function (imgId, imgUrl) {
			$('#atx-vb-edit-icon-custom-id').val(imgId);
			renderCustomIcon(imgUrl);
			$('#atx-vb-remove-icon').show();

			let id = parseInt($('#atx-vb-edit-id').val(), 10);
			if (!VB.extras[id]) VB.extras[id] = {};
			VB.extras[id].custom_icon_id = imgId;
			VB.extras[id].custom_icon_url = imgUrl;
			saveExtras(id, true);
		});
	});

	$(document).on('click', '#atx-vb-remove-icon', function () {
		$('#atx-vb-edit-icon-custom-id').val('');
		$('#atx-vb-custom-icon-preview').html('');
		$(this).hide();

		let id = parseInt($('#atx-vb-edit-id').val(), 10);
		if (!VB.extras[id]) VB.extras[id] = {};
		VB.extras[id].custom_icon_id = 0;
		VB.extras[id].custom_icon_url = '';
		saveExtras(id, true);
	});

	function renderCustomIcon(url) {
		if (url) {
			$('#atx-vb-custom-icon-preview').html(`<img src="${url}" style="width:100%;height:100%;object-fit:contain;" />`);
			$('#atx-vb-remove-icon').show();
		} else {
			$('#atx-vb-custom-icon-preview').html('');
			$('#atx-vb-remove-icon').hide();
		}
	}

	// ── Stage extras with the rest of the menu ──

	function saveExtras(itemId, refreshPreview) {
		let data = VB.extras[itemId] || {};
		let item = VB.getItem(itemId);
		if (item) item.extras = data;
		VB.markDirty();
		clearTimeout(VB._extrasPreviewTimer);
		VB._extrasPreviewTimer = setTimeout(function () {
			VB.refreshPreview();
		}, refreshPreview ? 0 : 350);
	}

})(jQuery);
