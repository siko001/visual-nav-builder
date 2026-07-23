/**
 * Visual Builder - Workspace
 * Staged changes, undo/redo, crash recovery, diagnostics, revisions and bulk tools.
 */
(function ($) {
	'use strict';

	let VB = window.AtxVB;
	let temporaryId = 2000000000;
	let history = [];
	let historyIndex = -1;
	let cleanSignature = '';
	let captureTimer = null;
	let draftTimer = null;
	let applyingSnapshot = false;
	let pendingDraft = null;
	let pendingConflictAction = null;

	VB.bulkMode = false;
	VB.bulkSelection = new Set();

	function clone(value) {
		return JSON.parse(JSON.stringify(value));
	}

	function nextTemporaryId() {
		do {
			temporaryId++;
		} while (VB.getItem(temporaryId));
		return temporaryId;
	}

	function syncExtrasIntoItems() {
		VB.items.forEach(function (item) {
			item.extras = clone(VB.extras[item.id] || item.extras || {});
		});
	}

	function rebuildExtras() {
		VB.extras = {};
		VB.items.forEach(function (item) {
			item.extras = item.extras || {};
			VB.extras[item.id] = item.extras;
		});
	}

	function getSnapshot() {
		syncExtrasIntoItems();
		return {
			items: clone(VB.items),
			menuName: VB.menuName || '',
		};
	}

	function snapshotSignature(snapshot) {
		return JSON.stringify(snapshot);
	}

	function resetHistory() {
		history = [getSnapshot()];
		historyIndex = 0;
		cleanSignature = snapshotSignature(history[0]);
		updateUndoButtons();
	}

	function scheduleSnapshot() {
		if (applyingSnapshot) return;
		clearTimeout(captureTimer);
		captureTimer = setTimeout(captureSnapshot, 220);
	}

	function captureSnapshot() {
		let snapshot = getSnapshot();
		if (historyIndex >= 0 && snapshotSignature(history[historyIndex]) === snapshotSignature(snapshot)) {
			VB.dirty = snapshotSignature(snapshot) !== cleanSignature;
			$('#atx-vb-status')
				.text(VB.dirty ? 'Unsaved changes' : '')
				.css('color', VB.dirty ? '#f0ad4e' : '');
			if (!VB.dirty) VB.discardDraft();
			return;
		}

		history = history.slice(0, historyIndex + 1);
		history.push(snapshot);
		if (history.length > 100) {
			history.shift();
		}
		historyIndex = history.length - 1;
		updateUndoButtons();
	}

	function applySnapshot(snapshot) {
		if (!snapshot) return;
		applyingSnapshot = true;
		VB.items = clone(snapshot.items || []);
		VB.setMenuName(snapshot.menuName || '');
		rebuildExtras();
		VB.recalcPositions();

		if (VB.selectedId && !VB.getItem(VB.selectedId)) {
			VB.selectedId = null;
			$('#atx-vb-editor').hide();
		}

		VB.renderTree();
		if (VB.selectedId) {
			VB.openEditor(VB.getItem(VB.selectedId));
		}
		VB.dirty = snapshotSignature(snapshot) !== cleanSignature;
		$('#atx-vb-status')
			.text(VB.dirty ? 'Unsaved changes' : '')
			.css('color', VB.dirty ? '#f0ad4e' : '');
		VB.refreshPreview();
		applyingSnapshot = false;
		if (VB.dirty) {
			scheduleDraft();
		} else {
			VB.discardDraft();
		}
	}

	function updateUndoButtons() {
		$('#atx-vb-undo').prop('disabled', historyIndex <= 0);
		$('#atx-vb-redo').prop('disabled', historyIndex < 0 || historyIndex >= history.length - 1);
	}

	VB.undo = function () {
		clearTimeout(captureTimer);
		captureSnapshot();
		if (historyIndex <= 0) return;
		historyIndex--;
		applySnapshot(history[historyIndex]);
		updateUndoButtons();
	};

	VB.redo = function () {
		if (historyIndex >= history.length - 1) return;
		historyIndex++;
		applySnapshot(history[historyIndex]);
		updateUndoButtons();
	};

	let originalMarkDirty = VB.markDirty;
	VB.markDirty = function () {
		originalMarkDirty.call(VB);
		scheduleSnapshot();
		scheduleDraft();
	};

	function draftKey(location) {
		return 'atx_vb_draft:' + window.location.host + ':' + (location || VB.menuLocation || '');
	}

	function scheduleDraft() {
		if (applyingSnapshot || pendingDraft) return;
		clearTimeout(draftTimer);
		draftTimer = setTimeout(saveDraft, 350);
	}

	function saveDraft() {
		if (!VB.dirty) return;
		let draft = {
			version: 1,
			location: VB.menuLocation,
			baseHash: VB.baseHash || '',
			savedAt: new Date().toISOString(),
			state: getSnapshot(),
		};
		try {
			window.localStorage.setItem(draftKey(), JSON.stringify(draft));
		} catch (e) {
			// The builder still works if storage is blocked or full.
		}
	}

	VB.discardDraft = function (location) {
		try {
			window.localStorage.removeItem(draftKey(location));
		} catch (e) {
			// Storage can be unavailable in hardened browsers.
		}
		pendingDraft = null;
		$('#atx-vb-recovery').prop('hidden', true);
	};

	function findRecoveryDraft() {
		pendingDraft = null;
		try {
			let stored = window.localStorage.getItem(draftKey());
			if (stored) {
				let parsed = JSON.parse(stored);
				if (parsed && parsed.state && Array.isArray(parsed.state.items)) {
					pendingDraft = parsed;
				}
			}
		} catch (e) {
			pendingDraft = null;
		}

		if (!pendingDraft) {
			$('#atx-vb-recovery').prop('hidden', true);
			return;
		}

		let saved = pendingDraft.savedAt ? new Date(pendingDraft.savedAt).toLocaleString() : 'earlier';
		let changed = pendingDraft.baseHash && pendingDraft.baseHash !== VB.baseHash
			? ' The saved menu has changed since this draft was created.'
			: '';
		$('#atx-vb-recovery-message').text('Unsaved work from ' + saved + ' is available.' + changed);
		$('#atx-vb-recovery').prop('hidden', false);
	}

	$('#atx-vb-recovery-restore').on('click', function () {
		if (!pendingDraft) return;
		history = [getSnapshot(), clone(pendingDraft.state)];
		historyIndex = 1;
		applySnapshot(history[historyIndex]);
		updateUndoButtons();
		pendingDraft = null;
		$('#atx-vb-recovery').prop('hidden', true);
		VB.notify('Unsaved draft restored.', 'success');
	});

	$('#atx-vb-recovery-discard').on('click', function () {
		VB.discardDraft();
		VB.notify('Unsaved draft discarded.', 'success');
	});

	VB.afterLoad = function () {
		rebuildExtras();
		VB.bulkSelection.clear();
		VB.bulkMode = false;
		$('#atx-vb').removeClass('atx-vb--bulk-mode');
		$('#atx-vb-bulk-toolbar').prop('hidden', true);
		$('#atx-vb-copy-location option').prop('disabled', false);
		$('#atx-vb-copy-location option[value="' + VB.menuLocation + '"]').prop('disabled', true);
		$('#atx-vb-copy-location').val('');
		resetHistory();
		findRecoveryDraft();
		renderHistory();
	};

	VB.afterSave = function (data) {
		let oldSelected = VB.selectedId;
		let idMap = data.id_map || {};
		VB.items = data.items || VB.items;
		VB.baseHash = data.base_hash || VB.baseHash;
		VB.revisions = data.revisions || [];
		VB.selectedId = parseInt(idMap[String(oldSelected)] || oldSelected || 0, 10) || null;
		VB.setMenuName(data.menu_name || VB.menuName);
		rebuildExtras();
		VB.dirty = false;
		VB.discardDraft();
		resetHistory();
		VB.renderTree();
		if (VB.selectedId && VB.getItem(VB.selectedId)) {
			VB.openEditor(VB.getItem(VB.selectedId));
		}
		renderHistory();
	};

	VB.addLocalItem = function (source, parentId) {
		source = source || {};
		let id = nextTemporaryId();
		let item = {
			id: id,
			title: source.title || 'New Item',
			url: source.url || '#',
			parent_id: parseInt(parentId, 10) || 0,
			position: VB.items.length + 1,
			classes: clone(source.classes || []),
			type: source.type || 'custom',
			object: source.object || 'custom',
			object_id: parseInt(source.object_id, 10) || 0,
			acf: clone(source.acf || {}),
			icon: source.icon || '',
			extras: clone(source.extras || {}),
			is_new: true,
		};

		VB.items.push(item);
		VB.extras[id] = item.extras;
		VB.selectedId = id;
		VB.recalcPositions();
		VB.renderTree();
		VB.openEditor(item);
		if (VB.updateExistingChildActions) VB.updateExistingChildActions();
		VB.markDirty();
		VB.refreshPreview();
		return item;
	};

	function collectBranchIds(rootId, output) {
		output = output || [];
		if (output.indexOf(rootId) !== -1) return output;
		output.push(rootId);
		VB.getChildren(rootId).forEach(function (child) {
			collectBranchIds(child.id, output);
		});
		return output;
	}

	function selectedRoots(ids) {
		let selected = new Set((ids || []).map(Number));
		return Array.from(selected).filter(function (id) {
			let item = VB.getItem(id);
			while (item && item.parent_id) {
				if (selected.has(item.parent_id)) return false;
				item = VB.getItem(item.parent_id);
			}
			return true;
		});
	}

	VB.deleteLocalBranches = function (ids) {
		let removeIds = [];
		selectedRoots(ids).forEach(function (id) {
			collectBranchIds(id, removeIds);
		});
		let removeSet = new Set(removeIds);
		VB.items = VB.items.filter(function (item) {
			if (!removeSet.has(item.id)) return true;
			delete VB.extras[item.id];
			VB.bulkSelection.delete(item.id);
			return false;
		});
		if (VB.selectedId && removeSet.has(VB.selectedId)) {
			VB.selectedId = null;
			$('#atx-vb-editor').hide();
		}
		VB.recalcPositions();
		VB.renderTree();
		if (VB.updateExistingChildActions) VB.updateExistingChildActions();
		VB.markDirty();
		VB.refreshPreview();
	};

	VB.duplicateBranches = function (ids) {
		let roots = selectedRoots(ids);
		let added = [];

		roots.forEach(function (rootId) {
			let branchIds = collectBranchIds(rootId, []);
			let idMap = {};
			branchIds.forEach(function (oldId) {
				idMap[oldId] = nextTemporaryId();
			});

			branchIds.forEach(function (oldId) {
				let original = VB.getItem(oldId);
				if (!original) return;
				let duplicate = clone(original);
				duplicate.id = idMap[oldId];
				duplicate.parent_id = oldId === rootId
					? original.parent_id
					: (idMap[original.parent_id] || 0);
				duplicate.position = VB.items.length + added.length + 1;
				duplicate.title = oldId === rootId ? (original.title || 'Item') + ' Copy' : original.title;
				duplicate.is_new = true;
				duplicate.extras = clone(VB.extras[oldId] || original.extras || {});
				VB.extras[duplicate.id] = duplicate.extras;
				added.push(duplicate);
			});
		});

		if (!added.length) return;
		VB.items = VB.items.concat(added);
		VB.recalcPositions();
		VB.renderTree();
		VB.selectedId = added[0].id;
		VB.openEditor(added[0]);
		VB.markDirty();
		VB.refreshPreview();
		VB.notify(added.length === 1 ? 'Branch duplicated.' : added.length + ' items duplicated.', 'success');
	};

	$('#atx-vb-duplicate-item').on('click', function () {
		let id = parseInt($('#atx-vb-edit-id').val(), 10);
		if (id) VB.duplicateBranches([id]);
	});

	$('#atx-vb-undo').on('click', VB.undo);
	$('#atx-vb-redo').on('click', VB.redo);

	$(document).on('keydown.atxWorkspace', function (event) {
		let editable = $(event.target).is('input, textarea, select, [contenteditable="true"]');
		if (editable || !(event.metaKey || event.ctrlKey)) return;
		let key = String(event.key || '').toLowerCase();
		if (key === 'z') {
			event.preventDefault();
			event.shiftKey ? VB.redo() : VB.undo();
		} else if (key === 'y') {
			event.preventDefault();
			VB.redo();
		}
	});

	// ── Bulk edit ──

	VB.toggleBulkItem = function (id, selected) {
		selected ? VB.bulkSelection.add(id) : VB.bulkSelection.delete(id);
		VB.updateBulkToolbar();
	};

	VB.updateBulkToolbar = function () {
		let validIds = new Set(VB.items.map(function (item) { return item.id; }));
		VB.bulkSelection.forEach(function (id) {
			if (!validIds.has(id)) VB.bulkSelection.delete(id);
		});
		$('#atx-vb-bulk-count').text(VB.bulkSelection.size);
		$('#atx-vb-bulk-move, #atx-vb-bulk-duplicate, #atx-vb-bulk-copy, #atx-vb-bulk-delete')
			.prop('disabled', VB.bulkSelection.size === 0);
		renderBulkParents();
	};

	function renderBulkParents() {
		let roots = selectedRoots(Array.from(VB.bulkSelection));
		let blocked = new Set();
		roots.forEach(function (id) {
			collectBranchIds(id, []).forEach(function (branchId) {
				blocked.add(branchId);
			});
		});

		let $select = $('#atx-vb-bulk-parent').html('<option value="0">Move to root</option>');
		VB.items.forEach(function (item) {
			if (blocked.has(item.id)) return;
			let depth = VB.getDepth(item.id);
			$select.append(
				$('<option></option>')
					.val(item.id)
					.text(new Array(depth + 1).join('— ') + (item.title || 'Untitled'))
			);
		});
	}

	function setBulkMode(enabled) {
		VB.bulkMode = Boolean(enabled);
		if (!VB.bulkMode) VB.bulkSelection.clear();
		$('#atx-vb').toggleClass('atx-vb--bulk-mode', VB.bulkMode);
		$('#atx-vb-bulk-toolbar').prop('hidden', !VB.bulkMode);
		$('#atx-vb-bulk-toggle').toggleClass('button-primary', VB.bulkMode);
		VB.renderTree();
	}

	$('#atx-vb-bulk-toggle').on('click', function () {
		setBulkMode(!VB.bulkMode);
	});

	$('#atx-vb-bulk-done').on('click', function () {
		setBulkMode(false);
	});

	$('#atx-vb-bulk-move').on('click', function () {
		let parentId = parseInt($('#atx-vb-bulk-parent').val(), 10) || 0;
		let roots = selectedRoots(Array.from(VB.bulkSelection));
		if (!roots.length) return;
		let changed = false;
		roots.forEach(function (id) {
			let item = VB.getItem(id);
			if (item && item.parent_id !== parentId) {
				item.parent_id = parentId;
				changed = true;
			}
		});
		if (!changed) {
			VB.notify('The selected branches are already there.', 'success');
			return;
		}
		VB.recalcPositions();
		VB.renderTree();
		VB.markDirty();
		VB.refreshPreview();
		VB.notify('Selected branches moved.', 'success');
	});

	$('#atx-vb-bulk-duplicate').on('click', function () {
		VB.duplicateBranches(Array.from(VB.bulkSelection));
	});

	$('#atx-vb-bulk-delete').on('click', function () {
		let roots = selectedRoots(Array.from(VB.bulkSelection));
		if (!roots.length || !confirm('Delete the selected branches and all of their children?')) return;
		VB.deleteLocalBranches(roots);
	});

	function buildCopyPayload() {
		let payload = [];
		selectedRoots(Array.from(VB.bulkSelection)).forEach(function (rootId) {
			let idMap = {};
			let branchIds = collectBranchIds(rootId, []);
			branchIds.forEach(function (id) {
				idMap[id] = nextTemporaryId();
			});
			branchIds.forEach(function (id) {
				let source = VB.getItem(id);
				if (!source) return;
				let item = clone(source);
				item.id = idMap[id];
				item.parent_id = id === rootId ? 0 : (idMap[source.parent_id] || 0);
				item.extras = clone(VB.extras[id] || source.extras || {});
				item.is_new = true;
				payload.push(item);
			});
		});
		return payload;
	}

	$('#atx-vb-bulk-copy').on('click', function () {
		let target = $('#atx-vb-copy-location').val();
		let label = $('#atx-vb-copy-location option:selected').text();
		let payload = buildCopyPayload();
		if (!target) {
			VB.notify('Choose a target menu location.', 'error');
			return;
		}
		if (!payload.length || !confirm('Copy the selected branches to ' + label + '? This updates that menu immediately.')) {
			return;
		}

		let $button = $(this).prop('disabled', true).text('Copying…');
		$.ajax({
			url: atxVB.ajaxUrl,
			method: 'POST',
			data: {
				action: 'atx_vb_copy_items',
				_wpnonce: atxVB.nonce,
				menu_location: VB.menuLocation,
				target_location: target,
				items: JSON.stringify(payload),
			},
			success: function (response) {
				if (response.success) {
					VB.notify(response.data.message || 'Items copied.', 'success');
				} else {
					VB.notify(response.data || 'Could not copy items.', 'error');
				}
			},
			error: function () {
				VB.notify('Could not copy items.', 'error');
			},
			complete: function () {
				$button.prop('disabled', false).text('Copy');
			},
		});
	});

	// ── Menu health ──

	$('#atx-vb-health-open').on('click', function () {
		$('#atx-vb-health-modal').prop('hidden', false);
		$('#atx-vb-health-summary').text('Checking the current staged menu…');
		$('#atx-vb-health-results').html('<p>Checking labels, destinations, content and external links…</p>');
		syncExtrasIntoItems();
		$.ajax({
			url: atxVB.ajaxUrl,
			method: 'POST',
			data: {
				action: 'atx_vb_health_check',
				_wpnonce: atxVB.nonce,
				menu_location: VB.menuLocation,
				items: JSON.stringify(VB.items),
			},
			success: function (response) {
				if (!response.success) {
					renderHealthError(response.data || 'Health check failed.');
					return;
				}
				renderHealth(response.data);
			},
			error: function () {
				renderHealthError('Health check failed.');
			},
		});
	});

	function renderHealth(data) {
		let warnings = data.warnings || [];
		let externalNote = data.external_skipped
			? ' ' + data.external_skipped + ' additional external links were skipped to keep the check fast.'
			: '';
		$('#atx-vb-health-summary').text(
			warnings.length
				? warnings.length + ' issue' + (warnings.length === 1 ? '' : 's') + ' across ' + data.item_count + ' items.' + externalNote
				: 'Everything looks healthy across ' + data.item_count + ' items.'
		);

		let $results = $('#atx-vb-health-results').html('');
		if (!warnings.length) {
			$results.html('<div class="atx-vb-health__success">✓ No menu issues found.</div>');
			return;
		}

		warnings.forEach(function (warning) {
			let $warning = $('<button type="button" class="atx-vb-health__warning"></button>')
				.addClass('atx-vb-health__warning--' + warning.severity)
				.attr('data-item-id', warning.item_id)
				.append($('<strong></strong>').text(warning.title || 'Untitled item'))
				.append($('<span></span>').text(warning.message || ''));
			$results.append($warning);
		});
	}

	function renderHealthError(message) {
		$('#atx-vb-health-summary').text('The check could not be completed.');
		$('#atx-vb-health-results').html($('<p class="atx-vb-health__error"></p>').text(message));
	}

	$(document).on('click', '.atx-vb-health__warning', function () {
		let id = parseInt($(this).attr('data-item-id'), 10);
		closeModal('health');
		selectItem(id);
	});

	// ── Revision history ──

	$('#atx-vb-history-open').on('click', function () {
		renderHistory();
		$('#atx-vb-history-modal').prop('hidden', false);
	});

	function renderHistory() {
		let $results = $('#atx-vb-history-results');
		if (!$results.length) return;
		$results.html('');
		if (!VB.revisions || !VB.revisions.length) {
			$results.html('<p>No saved revisions yet. A revision is created before each menu save.</p>');
			return;
		}
		VB.revisions.forEach(function (revision) {
			let $row = $('<div class="atx-vb-history__row"></div>');
			let details = $('<div class="atx-vb-history__details"></div>')
				.append($('<strong></strong>').text(revision.note || 'Saved menu'))
				.append($('<span></span>').text((revision.created_at || '') + (revision.user_name ? ' · ' + revision.user_name : '')));
			let $restore = $('<button type="button" class="button button-small">Restore</button>')
				.attr('data-revision-id', revision.id);
			$row.append(details, $restore);
			$results.append($row);
		});
	}

	$(document).on('click', '#atx-vb-history-results [data-revision-id]', function () {
		let revisionId = $(this).attr('data-revision-id');
		let message = 'Restore this saved revision? The current saved menu will be kept as another revision.';
		if (VB.dirty) {
			message += ' Your unsaved builder changes will be discarded.';
		}
		if (!confirm(message)) return;
		restoreRevision(revisionId, false);
	});

	function restoreRevision(revisionId, force) {
		$.ajax({
			url: atxVB.ajaxUrl,
			method: 'POST',
			data: {
				action: 'atx_vb_revisions',
				_wpnonce: atxVB.nonce,
				menu_location: VB.menuLocation,
				revision_id: revisionId,
				base_hash: VB.baseHash || '',
				force: force ? '1' : '',
			},
			success: function (response) {
				if (response.success) {
					closeModal('history');
					VB.afterSave(response.data);
					VB.refreshPreview();
					VB.notify(response.data.message || 'Revision restored.', 'success');
					return;
				}
				handleRevisionError(response.data, revisionId, force);
			},
			error: function (xhr) {
				let data = xhr && xhr.responseJSON ? xhr.responseJSON.data : null;
				handleRevisionError(data, revisionId, force);
			},
		});
	}

	function handleRevisionError(data, revisionId, force) {
		if (!force && data && data.code === 'edit_conflict') {
			VB.showConflict(data, function () {
				restoreRevision(revisionId, true);
			});
			return;
		}
		VB.notify((data && data.message) || data || 'Could not restore the revision.', 'error');
	}

	// ── Conflict protection and shared modal helpers ──

	VB.showConflict = function (data, overwriteAction) {
		pendingConflictAction = overwriteAction;
		$('#atx-vb-conflict-message').text(
			(data && data.message) || 'Another administrator changed this menu after you opened it.'
		);
		$('#atx-vb-conflict-modal').prop('hidden', false);
	};

	$('#atx-vb-conflict-keep').on('click', function () {
		pendingConflictAction = null;
		$('#atx-vb-conflict-modal').prop('hidden', true);
	});

	$('#atx-vb-conflict-reload').on('click', function () {
		pendingConflictAction = null;
		VB.discardDraft();
		$('#atx-vb-conflict-modal').prop('hidden', true);
		$('#atx-vb-status').text('Loading latest menu…').css('color', '#666');
		VB.load();
	});

	$('#atx-vb-conflict-overwrite').on('click', function () {
		let action = pendingConflictAction;
		pendingConflictAction = null;
		$('#atx-vb-conflict-modal').prop('hidden', true);
		if (action) action();
	});

	function closeModal(name) {
		$('#atx-vb-' + name + '-modal').prop('hidden', true);
	}

	$(document).on('click', '[data-atx-vb-close]', function () {
		closeModal($(this).attr('data-atx-vb-close'));
	});

	$(document).on('keydown.atxWorkspaceModal', function (event) {
		if (event.key !== 'Escape') return;
		closeModal('health');
		closeModal('history');
	});

	function selectItem(id) {
		let item = VB.getItem(id);
		if (!item) return;
		VB.selectedId = id;
		VB.openEditor(item);
		VB.renderTree();
		let element = document.querySelector('.atx-vb-item[data-id="' + id + '"]');
		if (element) element.scrollIntoView({ behavior: 'smooth', block: 'center' });
	}

})(jQuery);
