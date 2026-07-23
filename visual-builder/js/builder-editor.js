/**
 * Visual Builder - Editor Panel
 * Inline editing of selected menu item properties.
 */
(function ($) {
	'use strict';

	let VB = window.AtxVB;
	let previewTimer = null;

	/**
	 * Determine if an item supports icons.
	 * Icons only on categories:
	 * - Primary nav: depth 1 = category
	 * - Nested nav: depth 2 = category
	 * NOT on: depth 0 (top-level), depth 1 inside nested (tabs), sub-links
	 */
	function supportsIcon(item) {
		if (!VB.hasExtension || !VB.hasExtension('mega-nav')) return false;

		let depth = VB.getDepth(item.id);
		let isInsideNested = false;
		let current = item;
		while (current && current.parent_id) {
			let parent = VB.getItem(current.parent_id);
			if (parent && (parent.classes || []).includes('atx-nested-nav')) {
				isInsideNested = true;
				break;
			}
			current = parent;
		}

		// Primary: depth 1 = category
		// Nested: depth 2 = category
		let categoryDepth = isInsideNested ? 2 : 1;
		return depth === categoryDepth;
	}

	/**
	 * Determine if an item is a "sub-link" (leaf level that renders as plain text links).
	 * Primary: depth 2+ = sub-link
	 * Nested (atx-nested-nav parent): depth 3+ = sub-link
	 */
	function isSubLink(item) {
		if (!VB.hasExtension || !VB.hasExtension('mega-nav')) return false;

		let depth = VB.getDepth(item.id);

		// Check if any ancestor has atx-nested-nav
		let isInsideNested = false;
		let current = item;
		while (current && current.parent_id) {
			let parent = VB.getItem(current.parent_id);
			if (parent && (parent.classes || []).includes('atx-nested-nav')) {
				isInsideNested = true;
				break;
			}
			current = parent;
		}

		let maxCategoryDepth = isInsideNested ? 2 : 1;
		return depth > maxCategoryDepth;
	}

	VB.openEditor = function (item) {
		let $panel = $('#atx-vb-editor');
		$panel.show();

		$('#atx-vb-edit-id').val(item.id);
		$('#atx-vb-edit-title').val(item.title);
		$('#atx-vb-edit-url').val(item.url);
		$('#atx-vb-edit-classes').val((item.classes || []).join(' '));
		$('#atx-vb-editor-title').text('Edit: ' + item.title.replace('|', ' '));
		renderAcfFields(item);

		let subLink = isSubLink(item);
		let hasIcon = supportsIcon(item);

		// Icon: only on category-level items
		$('#atx-vb-edit-icon').closest('.atx-vb__field').toggle(hasIcon);
		$('#atx-vb-custom-icon-wrap').toggle(false);

		// CSS classes and add child: hide for sub-links
		$('#atx-vb-edit-classes').closest('.atx-vb__field').toggle(!subLink);
		$('#atx-vb-add-child').toggle(!subLink);

		// Hide hr separators when their adjacent sections are hidden
		// (will be updated after extras module runs too)
		setTimeout(function () {
			$('#atx-vb-editor .atx-vb__editor-body hr').each(function () {
				let $hr = $(this);
				let $prev = $hr.prev();
				let $next = $hr.next();
				let prevVisible = $prev.length ? $prev.is(':visible') : false;
				let nextVisible = $next.length ? $next.is(':visible') : false;
				$hr.toggle(prevVisible && nextVisible);
			});
		}, 50);

		if (hasIcon) {
			// Populate icon dropdown
			let $iconSelect = $('#atx-vb-edit-icon');
			$iconSelect.html('<option value="">— No Icon —</option>');
			$iconSelect.append('<option value="custom">Upload Custom Icon</option>');
			Object.keys(atxVB.icons).forEach(key => {
				let selected = item.icon === key ? ' selected' : '';
				$iconSelect.append(`<option value="${key}"${selected}>${atxVB.icons[key].label}</option>`);
			});

			// Show custom icon upload if "custom" is selected
			let isCustom = item.icon === 'custom';
			$('#atx-vb-custom-icon-wrap').toggle(isCustom);

			// Icon preview
			updateIconPreview(item.icon);
		}
	};

	function updateIconPreview(key) {
		let $preview = $('#atx-vb-edit-icon-preview');
		if (key && atxVB.icons[key]) {
			$preview.html(atxVB.icons[key].svg);
		} else {
			$preview.html('');
		}
	}

	function renderAcfFields(item) {
		let fields = atxVB.acfFields || [];
		let $section = $('#atx-vb-acf-section');
		let $wrap = $('#atx-vb-acf-fields').html('');

		if (!fields.length) {
			$section.hide();
			return;
		}

		$section.show();
		item.acf = item.acf || {};

		fields.forEach(field => {
			$wrap.append(renderAcfControl(field, getAcfValue(item, field)));
		});
		toggleAcfConditionalFields();
	}

	function renderAcfControl(field, value) {
		let instructions = field.instructions ? `<em class="atx-vb__acf-instructions">${escHtml(field.instructions)}</em>` : '';
		let required = field.required ? ' <abbr class="atx-vb__required" title="required">*</abbr>' : '';
		let input = '';

		if (field.type === 'true_false') {
			let checked = isCheckedValue(value) ? ' checked' : '';
			input = `
				<label class="atx-vb__acf-toggle">
					<input type="checkbox" class="atx-vb-acf-field" data-acf-name="${escAttr(field.name)}" data-acf-type="${escAttr(field.type)}"${checked} />
					<span>${escHtml(field.label)}${required}</span>
				</label>
			`;
			return acfWrap(field, input + instructions);
		}

		if (field.type === 'textarea' || isUnsupportedComplexField(field.type)) {
			let textValue = typeof value === 'object' ? JSON.stringify(value, null, 2) : (value || '');
			let fieldClass = isUnsupportedComplexField(field.type) ? 'atx-vb-acf-readonly' : 'atx-vb-acf-field';
			let disabled = isUnsupportedComplexField(field.type) ? ' disabled' : '';
			let note = isUnsupportedComplexField(field.type) ? '<em class="atx-vb__acf-instructions">Complex ACF field. Manage this one in the WordPress menu item editor.</em>' : '';
			input = `<textarea class="widefat ${fieldClass}" rows="3" data-acf-name="${escAttr(field.name)}" data-acf-type="${escAttr(field.type)}" placeholder="${escAttr(field.placeholder || '')}"${disabled}>${escHtml(textValue)}</textarea>${note}`;
			return acfWrap(field, acfLabel(field, required) + input + instructions);
		}

		if (field.type === 'select') {
			let multiple = field.multiple ? ' multiple' : '';
			input = `<select class="widefat atx-vb-acf-field" data-acf-name="${escAttr(field.name)}" data-acf-type="${escAttr(field.type)}"${multiple}>${renderChoiceOptions(field, value)}</select>`;
			return acfWrap(field, acfLabel(field, required) + input + instructions);
		}

		if (field.type === 'checkbox') {
			input = `<div class="atx-vb__acf-choices">${renderChoiceCheckboxes(field, value)}</div>`;
			return acfWrap(field, acfLabel(field, required) + input + instructions);
		}

		if (field.type === 'radio' || field.type === 'button_group') {
			input = `<div class="atx-vb__acf-choices">${renderChoiceRadios(field, value)}</div>`;
			return acfWrap(field, acfLabel(field, required) + input + instructions);
		}

		if (field.type === 'image' || field.type === 'file') {
			return acfWrap(field, acfLabel(field, required) + renderAcfMediaField(field, value) + instructions);
		}

		if (field.type === 'gallery') {
			return acfWrap(field, acfLabel(field, required) + renderAcfGalleryField(field, value) + instructions);
		}

		if (field.type === 'link') {
			return acfWrap(field, acfLabel(field, required) + renderAcfLinkField(field, value) + instructions);
		}

		let inputType = getInputType(field.type);
		let attrs = [
			`type="${inputType}"`,
			`class="widefat atx-vb-acf-field"`,
			`data-acf-name="${escAttr(field.name)}"`,
			`data-acf-type="${escAttr(field.type)}"`,
			`value="${escAttr(value || '')}"`,
			`placeholder="${escAttr(field.placeholder || '')}"`,
		];
		['min', 'max', 'step'].forEach(attr => {
			if (field[attr] !== undefined && field[attr] !== '') attrs.push(`${attr}="${escAttr(field[attr])}"`);
		});

		input = `<input ${attrs.join(' ')} />`;
		return acfWrap(field, acfLabel(field, required) + input + instructions);
	}

	function acfWrap(field, html) {
		return `<div class="atx-vb__acf-field" data-acf-control="${escAttr(field.name)}" data-acf-key="${escAttr(field.key || '')}">${html}</div>`;
	}

	function acfLabel(field, required) {
		return `<span>${escHtml(field.label)}${required}</span>`;
	}

	function getAcfValue(item, field) {
		let value = item.acf[field.name];
		return value !== undefined && value !== null ? value : (field.default_value ?? '');
	}

	function getInputType(type) {
		if (['number', 'range', 'email', 'url', 'password', 'date', 'time', 'color'].includes(type)) return type;
		return 'text';
	}

	function isUnsupportedComplexField(type) {
		return ['group', 'repeater', 'flexible_content', 'clone', 'google_map'].includes(type);
	}

	function renderChoiceOptions(field, value) {
		let values = Array.isArray(value) ? value.map(String) : [String(value || '')];
		let options = field.allow_null || !field.multiple ? '<option value="">— Select —</option>' : '';
		Object.keys(field.choices || {}).forEach(key => {
			let selected = values.includes(String(key)) ? ' selected' : '';
			options += `<option value="${escAttr(key)}"${selected}>${escHtml(field.choices[key])}</option>`;
		});
		return options;
	}

	function renderChoiceCheckboxes(field, value) {
		let values = Array.isArray(value) ? value.map(String) : [String(value || '')];
		return Object.keys(field.choices || {}).map(key => {
			let checked = values.includes(String(key)) ? ' checked' : '';
			return `<label><input type="checkbox" class="atx-vb-acf-choice" data-acf-name="${escAttr(field.name)}" value="${escAttr(key)}"${checked} /> <span>${escHtml(field.choices[key])}</span></label>`;
		}).join('');
	}

	function renderChoiceRadios(field, value) {
		let radios = field.allow_null ? `<label><input type="radio" class="atx-vb-acf-choice" name="acf-${escAttr(field.name)}" data-acf-name="${escAttr(field.name)}" value=""${!value ? ' checked' : ''} /> <span>None</span></label>` : '';
		Object.keys(field.choices || {}).forEach(key => {
			let checked = String(value || '') === String(key) ? ' checked' : '';
			radios += `<label><input type="radio" class="atx-vb-acf-choice" name="acf-${escAttr(field.name)}" data-acf-name="${escAttr(field.name)}" value="${escAttr(key)}"${checked} /> <span>${escHtml(field.choices[key])}</span></label>`;
		});
		return radios;
	}

	function renderAcfMediaField(field, value) {
		let media = typeof value === 'object' && value !== null ? value : { id: value || 0, url: '', title: '' };
		let preview = media.url
			? `<img src="${escAttr(media.url)}" alt="" />`
			: `<span>${media.id ? `Attachment #${escHtml(media.id)}` : 'No file selected'}</span>`;

		return `
			<div class="atx-vb__acf-media" data-acf-media="${escAttr(field.name)}">
				<input type="hidden" class="atx-vb-acf-field" data-acf-name="${escAttr(field.name)}" data-acf-type="${escAttr(field.type)}" value="${escAttr(media.id || '')}" data-url="${escAttr(media.url || '')}" />
				<div class="atx-vb__acf-media-preview">${preview}</div>
				<button type="button" class="button button-small atx-vb-acf-media-choose">Choose</button>
				<button type="button" class="button-link atx-vb-acf-media-remove">Remove</button>
			</div>
		`;
	}

	function renderAcfGalleryField(field, value) {
		let items = Array.isArray(value) ? value : [];
		let thumbs = items.map(item => {
			let media = typeof item === 'object' && item !== null ? item : { id: item, url: '' };
			return `
				<div class="atx-vb__acf-gallery-item" data-gallery-id="${escAttr(media.id || '')}" data-gallery-url="${escAttr(media.url || '')}">
					${media.url ? `<img src="${escAttr(media.url)}" alt="" />` : `<span>#${escHtml(media.id || '')}</span>`}
					<button type="button" class="button-link atx-vb-acf-gallery-remove" aria-label="Remove image">&times;</button>
				</div>
			`;
		}).join('');

		return `
			<div class="atx-vb__acf-gallery" data-acf-gallery="${escAttr(field.name)}">
				<div class="atx-vb__acf-gallery-grid">${thumbs || '<em>No images selected</em>'}</div>
				<button type="button" class="button button-small atx-vb-acf-gallery-add">Add Image</button>
			</div>
		`;
	}

	function renderAcfLinkField(field, value) {
		let link = typeof value === 'object' && value !== null ? value : {};
		return `
			<div class="atx-vb__acf-link" data-acf-link="${escAttr(field.name)}">
				<input type="url" class="widefat atx-vb-acf-link-part" data-acf-name="${escAttr(field.name)}" data-link-part="url" value="${escAttr(link.url || '')}" placeholder="URL" />
				<input type="text" class="widefat atx-vb-acf-link-part" data-acf-name="${escAttr(field.name)}" data-link-part="title" value="${escAttr(link.title || '')}" placeholder="Link text" />
				<label><input type="checkbox" class="atx-vb-acf-link-part" data-acf-name="${escAttr(field.name)}" data-link-part="target" value="_blank"${link.target === '_blank' ? ' checked' : ''} /> Open in new tab</label>
			</div>
		`;
	}

	function isCheckedValue(value) {
		return value === true || value === '1' || value === 1;
	}

	function toggleAcfConditionalFields() {
		let fields = atxVB.acfFields || [];
		fields.forEach(field => {
			let visible = evaluateAcfConditionalLogic(field, fields);
			$(`[data-acf-control="${field.name}"]`).toggle(visible);
		});
	}

	function evaluateAcfConditionalLogic(field, fields) {
		if (!field.conditional_logic || !Array.isArray(field.conditional_logic)) return true;

		return field.conditional_logic.some(group => {
			return (group || []).every(rule => {
				let controller = fields.find(candidate => candidate.key === rule.field || candidate.name === rule.field);
				if (!controller) return true;

				let value = getCurrentAcfControlValue(controller);
				return compareAcfRule(value, rule.operator || '==', rule.value);
			});
		});
	}

	function compareAcfRule(value, operator, expected) {
		if (Array.isArray(value)) {
			let contains = value.map(String).includes(String(expected));
			return operator === '!=' ? !contains : contains;
		}

		let actual = String(value ?? '');
		let target = String(expected ?? '');
		return operator === '!=' ? actual !== target : actual === target;
	}

	function getCurrentAcfControlValue(field) {
		let $control = $(`[data-acf-control="${field.name}"]`);
		if (field.type === 'true_false') return $control.find('.atx-vb-acf-field').is(':checked') ? '1' : '';
		if (field.type === 'checkbox') return $control.find('.atx-vb-acf-choice:checked').map(function () { return this.value; }).get();
		if (field.type === 'radio' || field.type === 'button_group') return $control.find('.atx-vb-acf-choice:checked').val() || '';
		if (field.type === 'link') return collectAcfLinkValue(field.name).url || '';
		return $control.find('.atx-vb-acf-field').val() || '';
	}

	VB.validateRequiredAcf = function () {
		let fields = (atxVB.acfFields || []).filter(field => field.required && !isUnsupportedComplexField(field.type));
		clearAcfValidationState();

		for (let item of this.items) {
			let invalid = fields.find(field => {
				if (!evaluateAcfConditionalLogicForItem(field, item, atxVB.acfFields || [])) return false;
				return isAcfValueEmpty(getAcfItemValue(item, field), field);
			});

			if (invalid) {
				this.selectedId = item.id;
				this.openEditor(item);
				$('.atx-vb-item--active').removeClass('atx-vb-item--active');
				$(`.atx-vb-item[data-id="${item.id}"]`).addClass('atx-vb-item--active');

				setTimeout(function () {
					let $control = $(`[data-acf-control="${invalid.name}"]`);
					$control.addClass('atx-vb__acf-field--invalid');
					$control.find('input, textarea, select, button').first().trigger('focus');
				}, 0);

				$('#atx-vb-status').text(`${item.title || 'Menu item'} needs ${invalid.label || invalid.name}.`).css('color', '#e44');
				if (this.notify) this.notify(`${item.title || 'Menu item'} needs ${invalid.label || invalid.name}.`, 'error');
				return false;
			}
		}

		return true;
	};

	function clearAcfValidationState() {
		$('.atx-vb__acf-field--invalid').removeClass('atx-vb__acf-field--invalid');
	}

	function evaluateAcfConditionalLogicForItem(field, item, fields) {
		if (!field.conditional_logic || !Array.isArray(field.conditional_logic)) return true;

		return field.conditional_logic.some(group => {
			return (group || []).every(rule => {
				let controller = fields.find(candidate => candidate.key === rule.field || candidate.name === rule.field);
				if (!controller) return true;

				let value = getAcfItemValue(item, controller);
				return compareAcfRule(value, rule.operator || '==', rule.value);
			});
		});
	}

	function getAcfItemValue(item, field) {
		let acf = item.acf || {};
		let value = acf[field.name];
		return value !== undefined && value !== null ? value : (field.default_value ?? '');
	}

	function isAcfValueEmpty(value, field) {
		if (field.type === 'true_false') return !isCheckedValue(value);
		if (field.type === 'image' || field.type === 'file') return !(typeof value === 'object' ? value.id : value);
		if (field.type === 'gallery') return !Array.isArray(value) || value.length === 0;
		if (field.type === 'link') return !(value && typeof value === 'object' && value.url);
		if (Array.isArray(value)) return value.length === 0;
		return String(value ?? '').trim() === '';
	}

	function escHtml(value) {
		let div = document.createElement('div');
		div.textContent = value || '';
		return div.innerHTML;
	}

	function escAttr(value) {
		return escHtml(value).replace(/"/g, '&quot;');
	}

	// Close editor
	$('#atx-vb-editor-close').on('click', function () {
		$('#atx-vb-editor').hide();
		VB.selectedId = null;
		$('.atx-vb-item--active').removeClass('atx-vb-item--active');
	});

	// Live update on field change
	$(document).on('input change', '#atx-vb-edit-title, #atx-vb-edit-url, #atx-vb-edit-classes, #atx-vb-edit-icon, .atx-vb-acf-field, .atx-vb-acf-choice, .atx-vb-acf-link-part, .atx-vb__acf-gallery', function () {
		let id = parseInt($('#atx-vb-edit-id').val(), 10);
		let item = VB.getItem(id);
		if (!item) return;

		item.title = $('#atx-vb-edit-title').val();
		item.url = $('#atx-vb-edit-url').val();
		item.classes = $('#atx-vb-edit-classes').val().split(/\s+/).filter(Boolean);
		item.icon = $('#atx-vb-edit-icon').val();
		item.acf = collectAcfValues();
		toggleAcfConditionalFields();
		clearAcfValidationState();

		// Update tree item title
		$(`.atx-vb-item[data-id="${id}"] .atx-vb-item__title`).text(item.title);
		updateIconPreview(item.icon);

		VB.markDirty();
		$('#atx-vb-status').text('Unsaved changes').css('color', '#f0ad4e');

		clearTimeout(previewTimer);
		previewTimer = setTimeout(function () {
			if (VB.refreshPreview) {
				VB.refreshPreview();
			}
		}, 350);
	});

	function collectAcfValues() {
		let values = {};
		(atxVB.acfFields || []).forEach(field => {
			if (isUnsupportedComplexField(field.type)) return;

			let $control = $(`[data-acf-control="${field.name}"]`);
			if (!$control.length) return;

			if (field.type === 'true_false') {
				values[field.name] = $control.find('.atx-vb-acf-field').is(':checked') ? '1' : '';
			} else if (field.type === 'checkbox') {
				values[field.name] = $control.find('.atx-vb-acf-choice:checked').map(function () { return this.value; }).get();
			} else if (field.type === 'radio' || field.type === 'button_group') {
				values[field.name] = $control.find('.atx-vb-acf-choice:checked').val() || '';
			} else if (field.type === 'select' && field.multiple) {
				values[field.name] = $control.find('.atx-vb-acf-field').val() || [];
			} else if (field.type === 'image' || field.type === 'file') {
				let $field = $control.find('.atx-vb-acf-field');
				values[field.name] = { id: $field.val() || '', url: $field.data('url') || '' };
			} else if (field.type === 'gallery') {
				values[field.name] = $control.find('.atx-vb__acf-gallery-item').map(function () {
					let $item = $(this);
					return { id: $item.data('gallery-id') || '', url: $item.data('gallery-url') || '' };
				}).get();
			} else if (field.type === 'link') {
				values[field.name] = collectAcfLinkValue(field.name);
			} else {
				values[field.name] = $control.find('.atx-vb-acf-field').val() || '';
			}
		});
		return values;
	}

	function collectAcfLinkValue(name) {
		let $wrap = $(`[data-acf-link="${name}"]`);
		return {
			url: $wrap.find('[data-link-part="url"]').val() || '',
			title: $wrap.find('[data-link-part="title"]').val() || '',
			target: $wrap.find('[data-link-part="target"]').is(':checked') ? '_blank' : '',
		};
	}

	$(document).on('click', '.atx-vb-acf-media-choose', function () {
		let $media = $(this).closest('.atx-vb__acf-media');
		if (!window.atxOpenImagePicker) return;

		window.atxOpenImagePicker(function (id, url) {
			$media.find('.atx-vb-acf-field').val(id).data('url', url).trigger('change');
			$media.find('.atx-vb__acf-media-preview').html(`<img src="${escAttr(url)}" alt="" />`);
		});
	});

	$(document).on('click', '.atx-vb-acf-media-remove', function () {
		let $media = $(this).closest('.atx-vb__acf-media');
		$media.find('.atx-vb-acf-field').val('').data('url', '').trigger('change');
		$media.find('.atx-vb__acf-media-preview').html('<span>No file selected</span>');
	});

	$(document).on('click', '.atx-vb-acf-gallery-add', function () {
		let $gallery = $(this).closest('.atx-vb__acf-gallery');
		if (!window.atxOpenImagePicker) return;

		window.atxOpenImagePicker(function (id, url) {
			let $grid = $gallery.find('.atx-vb__acf-gallery-grid');
			$grid.find('em').remove();
			$grid.append(`
				<div class="atx-vb__acf-gallery-item" data-gallery-id="${escAttr(id)}" data-gallery-url="${escAttr(url)}">
					<img src="${escAttr(url)}" alt="" />
					<button type="button" class="button-link atx-vb-acf-gallery-remove" aria-label="Remove image">&times;</button>
				</div>
			`);
			$gallery.trigger('change');
		});
	});

	$(document).on('click', '.atx-vb-acf-gallery-remove', function () {
		let $gallery = $(this).closest('.atx-vb__acf-gallery');
		$(this).closest('.atx-vb__acf-gallery-item').remove();
		if (!$gallery.find('.atx-vb__acf-gallery-item').length) {
			$gallery.find('.atx-vb__acf-gallery-grid').html('<em>No images selected</em>');
		}
		$gallery.trigger('change');
	});

	// Add child
	$('#atx-vb-add-child').on('click', function () {
		let parentId = parseInt($('#atx-vb-edit-id').val(), 10);
		if (!parentId) return;

		$.ajax({
			url: atxVB.ajaxUrl,
			method: 'POST',
			data: {
				action: 'atx_vb_add_item',
				_wpnonce: atxVB.nonce,
				menu_location: VB.menuLocation,
				title: 'New Item',
				parent_id: parentId,
			},
			success: function (res) {
				if (res.success) {
					VB.items.push({
						id: res.data.id,
						title: res.data.title,
						url: '#',
						parent_id: parentId,
						position: VB.items.length + 1,
						classes: [],
						type: 'custom',
						object: 'custom',
						object_id: res.data.id,
						acf: {},
						icon: '',
					});
					VB.renderTree();
					VB.markDirty();
					VB.refreshPreview(true);
				}
			}
		});
	});

	// Delete item
	$('#atx-vb-delete-item').on('click', function () {
		let id = parseInt($('#atx-vb-edit-id').val(), 10);
		let item = VB.getItem(id);
		if (!item) return;

		let children = VB.getChildren(id);
		let msg = children.length
			? `Delete "${item.title}" and its ${children.length} children?`
			: `Delete "${item.title}"?`;

		if (!confirm(msg)) return;

		// Collect all descendant IDs
		let idsToDelete = collectDescendantIds(id);
		idsToDelete.push(id);

		// Delete from server
		idsToDelete.forEach(delId => {
			$.ajax({
				url: atxVB.ajaxUrl,
				method: 'POST',
				data: { action: 'atx_vb_delete_item', _wpnonce: atxVB.nonce, menu_location: VB.menuLocation, item_id: delId }
			});
		});

		// Remove from local state
		VB.items = VB.items.filter(i => !idsToDelete.includes(i.id));

		$('#atx-vb-editor').hide();
		VB.selectedId = null;
		VB.renderTree();
		VB.markDirty();
		VB.refreshPreview();
	});

	function collectDescendantIds(parentId) {
		let ids = [];
		VB.getChildren(parentId).forEach(child => {
			ids.push(child.id);
			ids = ids.concat(collectDescendantIds(child.id));
		});
		return ids;
	}

})(jQuery);
