/**
 * Visual Builder - Actions
 * Save, add root item.
 */
(function ($) {
	'use strict';

	let VB = window.AtxVB;
	let existingSearchTimer = null;
	let existingSearchRequest = null;
	let existingSearchGeneration = 0;

	VB.saveMenu = function (force) {
		let $btn = $(this);
		if (!$btn.is('#atx-vb-save')) {
			$btn = $('#atx-vb-save');
		}
		VB.recalcPositions();

		if (VB.validateRequiredAcf && !VB.validateRequiredAcf()) {
			return;
		}

		let locationData = atxVB.locations && atxVB.locations[VB.menuLocation];
		VB.menuName = $.trim(
			$('#atx-vb-menu-name').val()
			|| VB.menuName
			|| (locationData && locationData.menu_name)
			|| ''
		);
		if (!VB.menuName) {
			$('#atx-vb-menu-name').trigger('focus');
			$('#atx-vb-status').text('Menu name cannot be empty.').css('color', '#e44');
			if (VB.notify) VB.notify('Menu name cannot be empty.', 'error');
			return null;
		}

		$btn.prop('disabled', true).text('Saving...');

		VB.items.forEach(function (item) {
			item.extras = VB.extras[item.id] || item.extras || {};
		});

		let request = $.ajax({
			url: atxVB.ajaxUrl,
			method: 'POST',
			data: {
				action: 'atx_vb_save',
				_wpnonce: atxVB.nonce,
				menu_location: VB.menuLocation,
				menu_name: VB.menuName,
				items: JSON.stringify(VB.items),
				base_hash: VB.baseHash || '',
				force: force ? '1' : '',
			},
			success: function (res) {
				$btn.prop('disabled', false).text('Save Changes');
				if (res.success) {
					if (VB.afterSave) {
						VB.afterSave(res.data);
					} else {
						if (VB.setMenuName) {
							VB.setMenuName(res.data.menu_name || VB.menuName);
						}
						VB.dirty = false;
					}
					$('#atx-vb-status').text('Saved!').css('color', '#8c8');
					if (VB.notify) VB.notify('Menu saved.', 'success');
					setTimeout(() => $('#atx-vb-status').text(''), 2000);
					VB.refreshPreview();
				} else {
					handleSaveError(res && res.data, force);
				}
			},
			error: function (xhr) {
				$btn.prop('disabled', false).text('Save Changes');
				let data = xhr && xhr.responseJSON ? xhr.responseJSON.data : null;
				handleSaveError(data, force);
			}
		});

		return request;
	};

	function handleSaveError(data, force) {
		let message = typeof data === 'object' && data ? data.message : data;
		message = message || 'Save failed.';
		if (!force && data && data.code === 'edit_conflict' && VB.showConflict) {
			VB.showConflict(data, function () {
				VB.saveMenu(true);
			});
			return;
		}
		$('#atx-vb-status').text(message).css('color', '#e44');
		if (VB.notify) VB.notify(message, 'error');
	}

	// Save all changes
	$('#atx-vb-save').on('click', function () {
		VB.saveMenu(false);
	});

	// Add root item
	$('#atx-vb-add-root').on('click', function () {
		addMenuItem({ title: 'New Item', url: '#', type: 'custom', object: 'custom', object_id: 0 }, 0);
	});

	function addMenuItem(source, parentId) {
		if (VB.addLocalItem) {
			VB.addLocalItem(source, parentId || 0);
		}
	}

	VB.openExistingItems = function () {
		$('#atx-vb').removeClass('atx-vb--tree-closed');
		window.localStorage.setItem('atx_vb_tree_closed', '0');
		$('#atx-vb-existing').removeClass('atx-vb-existing--hidden');
		$('#atx-vb-existing-search').trigger('focus');
		searchExistingItems();
	};

	VB.closeExistingItems = function () {
		$('#atx-vb-existing').addClass('atx-vb-existing--hidden');
	};

	$('#atx-vb-add-existing-toggle, #atx-vb-add-existing-child').on('click', function () {
		VB.openExistingItems();
	});

	$('#atx-vb-existing-close').on('click', function () {
		VB.closeExistingItems();
	});

	$('#atx-vb-existing-search').on('input', function () {
		clearTimeout(existingSearchTimer);
		existingSearchTimer = setTimeout(searchExistingItems, 250);
	});

	VB.updateExistingChildActions = function () {
		let parent = VB.selectedId ? VB.getItem(VB.selectedId) : null;
		let $buttons = $('.atx-vb-existing__add-child');

		$buttons.prop('hidden', !parent);
		if (!parent) {
			return;
		}

		let parentTitle = (parent.title || 'selected item').replace('|', ' ');
		$buttons
			.text('Add to ' + parentTitle)
			.attr('title', 'Add as a child of ' + parentTitle);
	};

	function searchExistingItems() {
		let query = $('#atx-vb-existing-search').val() || '';
		let $results = $('#atx-vb-existing-results').html('<div class="atx-vb-existing__empty">Searching...</div>');
		let generation = ++existingSearchGeneration;

		if (existingSearchRequest && existingSearchRequest.readyState !== 4) {
			existingSearchRequest.abort();
		}

		existingSearchRequest = $.ajax({
			url: atxVB.ajaxUrl,
			method: 'POST',
			data: {
				action: 'atx_vb_search_items',
				_wpnonce: atxVB.nonce,
				search: query,
			},
			success: function (res) {
				if (generation !== existingSearchGeneration) {
					return;
				}

				if (!res.success || !res.data || !res.data.items || !res.data.items.length) {
					$results.html('<div class="atx-vb-existing__empty">No matching items.</div>');
					return;
				}

				$results.html('');
				res.data.items.forEach(item => {
					let $row = $(`
						<div class="atx-vb-existing__item">
							<div class="atx-vb-existing__meta">
								<strong>${escHtml(item.title)}</strong>
								<span>${escHtml(item.group)}</span>
							</div>
							<div class="atx-vb-existing__actions">
								<button type="button" class="button button-small atx-vb-existing__add-root">Add Root</button>
								<button type="button" class="button button-small atx-vb-existing__add-child" hidden>Add Child</button>
							</div>
						</div>
					`);
					$row.find('.atx-vb-existing__add-root').on('click', () => addMenuItem(item, 0));
					$row.find('.atx-vb-existing__add-child').on('click', function () {
						let parentId = VB.selectedId || 0;
						if (parentId) {
							addMenuItem(item, parentId);
						}
					});
					$results.append($row);
				});
				VB.updateExistingChildActions();
			},
			error: function (xhr, status) {
				if (generation !== existingSearchGeneration || status === 'abort') {
					return;
				}
				$results.html('<div class="atx-vb-existing__empty">Could not load items.</div>');
			},
			complete: function () {
				if (generation === existingSearchGeneration) {
					existingSearchRequest = null;
				}
			}
		});
	}

	function escHtml(value) {
		let div = document.createElement('div');
		div.textContent = value || '';
		return div.innerHTML;
	}

	// Keyboard shortcut: Ctrl+S to save
	$(document).on('keydown', function (e) {
		if ((e.ctrlKey || e.metaKey) && e.key === 's') {
			e.preventDefault();
			$('#atx-vb-save').trigger('click');
		}
	});

	// Help modal
	$('#atx-vb-help-open').on('click', function (e) {
		e.preventDefault();
		$('#atx-vb-help-modal').show();
	});

	$(document).on('click', '.atx-vb-help__close, .atx-vb-help__backdrop', function () {
		$('#atx-vb-help-modal').hide();
	});

	$(document).on('keydown', function (e) {
		if (e.key === 'Escape') {
			$('#atx-vb-help-modal').hide();
			if (VB.closeExistingItems) VB.closeExistingItems();
		}
	});

})(jQuery);
