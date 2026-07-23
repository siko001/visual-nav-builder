/**
 * Visual Builder - Tree View
 * Renders the menu tree, handles drag/drop reordering, collapse/expand.
 */
(function ($) {
	'use strict';

	let VB = window.AtxVB;
	let collapsed = {};
	let dragState = null;
	const DEPTH_WIDTH = 28;
	const MAX_DEPTH = 6;
	const TREE_MIN_WIDTH = 260;
	const TREE_MAX_WIDTH = 720;

	VB.renderTree = function () {
		let $tree = $('#atx-vb-tree');
		$tree.html('');

		let hasHierarchy = this.items.some(item => Boolean(item.parent_id));
		$('#atx-vb-add-existing-toggle').prop('hidden', this.items.length > 0);
		$('#atx-vb-collapse-all, #atx-vb-expand-all').prop('hidden', !hasHierarchy);

		let roots = this.items.filter(i => !i.parent_id);
		roots.sort((a, b) => a.position - b.position);

		roots.forEach(item => renderItem($tree, item, 0));

		// Init sortable
		$tree.sortable({
			items: '> .atx-vb-item',
			handle: '.atx-vb-item__handle',
			placeholder: 'atx-vb-placeholder',
			helper: 'clone',
			appendTo: 'body',
			forceHelperSize: true,
			forcePlaceholderSize: false,
			tolerance: 'pointer',
			start: function (event, ui) {
				dragState = {
					id: parseInt(ui.item.data('id'), 10),
					depth: parseInt(ui.item.data('depth'), 10) || 0,
					x: event.pageX || 0,
					currentDepth: parseInt(ui.item.data('depth'), 10) || 0,
				};
				ui.helper.addClass('atx-vb-item--dragging');
			},
			sort: function (event, ui) {
				let depth = getDragDepth(event);
				dragState.currentDepth = depth;
				updateDropIndicator(ui.placeholder, depth);
				ui.helper
					.removeClass(function (index, className) {
						return (className.match(/atx-vb-item--depth-\d+/g) || []).join(' ');
					})
					.addClass('atx-vb-item--depth-' + depth);
			},
			stop: function (event, ui) {
				updateTreeFromDOM(ui);
				VB.renderTree();
				VB.markDirty();
				VB.refreshPreview();
				dragState = null;
			}
		});
	};

	function renderItem($parent, item, depth) {
		let children = VB.getChildren(item.id);
		children.sort((a, b) => a.position - b.position);

		let hasChildren = children.length > 0;
		let isCollapsed = collapsed[item.id] || false;
		let classes = (item.classes || []);
		let isPlaceholder = classes.includes('atx-placeholder');
		let isCTA = classes.includes('atx-nav-cta');
		let isNested = classes.includes('atx-nested-nav');
		let isPinned = VB.pinnedItems && VB.pinnedItems[item.id];

		let canPin = hasChildren;

		let iconHtml = '';
		if (item.icon && atxVB.icons[item.icon]) {
			iconHtml = atxVB.icons[item.icon].svg;
		}

		let pinHtml = canPin
			? `<button class="atx-vb-item__pin${isPinned ? ' atx-vb-item__pin--active' : ''}" data-id="${item.id}" title="${isPinned ? 'Unpin dropdown' : 'Pin dropdown open'}">📌</button>`
			: '';

		let titleDisplay = isPlaceholder ? '(placeholder)' : escHtml(item.title);

		let $item = $(`
			<div class="atx-vb-item atx-vb-item--depth-${depth}${isCTA ? ' atx-vb-item--cta' : ''}${isNested ? ' atx-vb-item--nested' : ''}${isPlaceholder ? ' atx-vb-item--placeholder' : ''}${VB.selectedId === item.id ? ' atx-vb-item--active' : ''}"
				data-id="${item.id}" data-depth="${depth}" data-parent="${item.parent_id || 0}">
				<span class="atx-vb-item__handle">⠿</span>
				${hasChildren ? `<button class="atx-vb-item__toggle">${isCollapsed ? '►' : '▼'}</button>` : '<span style="width:16px;"></span>'}
				${iconHtml ? `<span class="atx-vb-item__icon">${iconHtml}</span>` : ''}
				<span class="atx-vb-item__title">${titleDisplay}</span>
				${pinHtml}
				${hasChildren ? `<span class="atx-vb-item__badge">${children.length}</span>` : ''}
			</div>
		`);

		// Click to select
		$item.on('click', function (e) {
			if ($(e.target).hasClass('atx-vb-item__toggle') || $(e.target).hasClass('atx-vb-item__handle') || $(e.target).hasClass('atx-vb-item__pin')) return;
			VB.selectedId = item.id;
			VB.openEditor(item);
			$('.atx-vb-item--active').removeClass('atx-vb-item--active');
			$item.addClass('atx-vb-item--active');
			if (VB.updateExistingChildActions) {
				VB.updateExistingChildActions();
			}
		});

		// Toggle collapse
		$item.find('.atx-vb-item__toggle').on('click', function (e) {
			e.stopPropagation();
			collapsed[item.id] = !collapsed[item.id];
			VB.renderTree();
		});

		$parent.append($item);

		// Render children if not collapsed
		if (hasChildren && !isCollapsed) {
			children.forEach(child => renderItem($parent, child, depth + 1));
		}
	}

	function updateTreeFromDOM(ui) {
		let parentAtDepth = {};
		let previousDepth = 0;
		let draggedId = dragState ? dragState.id : 0;
		let draggedDepth = dragState ? dragState.currentDepth : 0;
		let orderedItems = [];
		let orderedIds = {};

		$('#atx-vb-tree .atx-vb-item').each(function (index) {
			let id = parseInt($(this).data('id'), 10);
			let item = VB.getItem(id);
			if (!item) return;

			let depth = id === draggedId
				? draggedDepth
				: parseInt($(this).attr('data-depth'), 10) || 0;

			if (index === 0) {
				depth = 0;
			}

			depth = Math.max(0, Math.min(depth, previousDepth + 1, MAX_DEPTH));

			let parentId = depth > 0 ? (parentAtDepth[depth - 1] || 0) : 0;
			if (parentId && isDescendant(parentId, id)) {
				parentId = 0;
				depth = 0;
			}

			item.parent_id = parentId;
			item.position = index + 1;
			orderedItems.push(item);
			orderedIds[id] = true;

			parentAtDepth[depth] = id;
			Object.keys(parentAtDepth).forEach(key => {
				if (parseInt(key, 10) > depth) delete parentAtDepth[key];
			});

			previousDepth = depth;
		});

		VB.items
			.filter(item => !orderedIds[item.id])
			.forEach(item => orderedItems.push(item));

		VB.items = orderedItems;
	}

	function getDragDepth(event) {
		if (!dragState || !event || !event.pageX) {
			return dragState ? dragState.depth : 0;
		}

		let delta = event.pageX - dragState.x;
		return Math.max(0, Math.min(dragState.depth + Math.round(delta / DEPTH_WIDTH), MAX_DEPTH));
	}

	function updateDropIndicator($placeholder, depth) {
		let left = 12 + (depth * 20);
		$placeholder.css({
			marginLeft: left + 'px',
			width: 'calc(100% - ' + (left + 12) + 'px)',
		});

		$placeholder.attr('data-depth-label', depth > 0 ? 'Child level ' + depth : 'Top level');
	}

	function isDescendant(candidateId, itemId) {
		let current = VB.getItem(candidateId);
		while (current && current.parent_id) {
			if (current.parent_id === itemId) {
				return true;
			}
			current = VB.getItem(current.parent_id);
		}
		return false;
	}

	function escHtml(str) {
		let div = document.createElement('div');
		div.textContent = str;
		return div.innerHTML;
	}

	// Collapse All
	$('#atx-vb-collapse-all').on('click', function () {
		VB.items.forEach(item => {
			if (VB.getChildren(item.id).length > 0) {
				collapsed[item.id] = true;
			}
		});
		VB.renderTree();
	});

	// Expand All
	$('#atx-vb-expand-all').on('click', function () {
		collapsed = {};
		VB.renderTree();
	});

	// ── Resizable / closable tree panel ──
	(function initTreePanelControls() {
		let $root = $('#atx-vb');
		let $panel = $('.atx-vb__tree-panel');
		let savedWidth = parseInt(window.localStorage.getItem('atx_vb_tree_width') || '', 10);
		let savedClosed = window.localStorage.getItem('atx_vb_tree_closed') === '1';
		let resizeState = null;

		if (savedWidth) {
			$panel.css('width', clamp(savedWidth, TREE_MIN_WIDTH, TREE_MAX_WIDTH) + 'px');
		}
		$root.toggleClass('atx-vb--tree-closed', savedClosed);

		$('#atx-vb-tree-close').on('click', function () {
			$root.addClass('atx-vb--tree-closed');
			window.localStorage.setItem('atx_vb_tree_closed', '1');
		});

		$('#atx-vb-tree-reopen').on('click', function () {
			$root.removeClass('atx-vb--tree-closed');
			window.localStorage.setItem('atx_vb_tree_closed', '0');
		});

		$('#atx-vb-tree-resize').on('mousedown', function (e) {
			e.preventDefault();
			resizeState = {
				x: e.clientX,
				width: $panel.outerWidth(),
			};
			$root.addClass('atx-vb--resizing-tree');
			$(document).on('mousemove.atxTreeResize', onResizeMove);
			$(document).on('mouseup.atxTreeResize', onResizeEnd);
		});

		function onResizeMove(e) {
			if (!resizeState) return;
			let width = clamp(resizeState.width + (e.clientX - resizeState.x), TREE_MIN_WIDTH, TREE_MAX_WIDTH);
			$panel.css('width', width + 'px');
		}

		function onResizeEnd() {
			if (!resizeState) return;
			window.localStorage.setItem('atx_vb_tree_width', String(Math.round($panel.outerWidth())));
			resizeState = null;
			$root.removeClass('atx-vb--resizing-tree');
			$(document).off('.atxTreeResize');
		}
	})();

	function clamp(value, min, max) {
		return Math.max(min, Math.min(max, value));
	}

	// ── Pin dropdown open ──
	VB.pinnedItems = {};

	$(document).on('click', '.atx-vb-item__pin', function (e) {
		e.stopPropagation();
		let id = parseInt($(this).data('id'), 10);
		let shouldPin = !VB.pinnedItems[id];

		if (shouldPin) {
			pinItemAndAncestors(id);
		} else {
			unpinItemAndDescendants(id);
		}

		syncPinButtons();
		applyPinsToIframe();
	});

	function pinItemAndAncestors(id) {
		let item = VB.getItem(id);

		while (item) {
			VB.pinnedItems[item.id] = true;
			item = item.parent_id ? VB.getItem(item.parent_id) : null;
		}
	}

	function unpinItemAndDescendants(id) {
		let branchIds = [id];

		for (let index = 0; index < branchIds.length; index++) {
			let parentId = branchIds[index];
			VB.items.forEach(function (item) {
				if (item.parent_id === parentId) {
					branchIds.push(item.id);
				}
			});
		}

		branchIds.forEach(function (branchId) {
			delete VB.pinnedItems[branchId];
		});
	}

	function syncPinButtons() {
		$('.atx-vb-item__pin').each(function () {
			let id = parseInt($(this).data('id'), 10);
			let isPinned = Boolean(VB.pinnedItems[id]);

			$(this)
				.toggleClass('atx-vb-item__pin--active', isPinned)
				.attr('title', isPinned ? 'Unpin dropdown and its children' : 'Pin dropdown open');
		});
	}

	function applyPinsToIframe() {
		try {
			let iframe = document.getElementById('atx-vb-preview');
			if (!iframe) return;
			let iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
			let $head = $(iframeDoc.head);

			// Remove old pin styles
			$head.find('#atx-vb-pin-styles').remove();

			let pinnedIds = Object.keys(VB.pinnedItems).filter(function (id) {
				return VB.pinnedItems[id];
			});

			$(iframeDoc.body).toggleClass('atx-vb-pin-preview-active', pinnedIds.length > 0);

			// Theme animation libraries (including GSAP) often leave menu text
			// at opacity: 0 until a real hover animation runs. Pinning is an
			// editing state, so make text deterministic while any item is pinned.
			let css = pinnedIds.length ? `
				body.atx-vb-pin-preview-active [data-atx-vb-menu-location] :is(
					a, button, span, p, li, small, strong, em,
					h1, h2, h3, h4, h5, h6
				),
				body.atx-vb-pin-preview-active #atx-nav-menu :is(
					a, button, span, p, li, small, strong, em,
					h1, h2, h3, h4, h5, h6
				),
				body.atx-vb-pin-preview-active [data-atx-vb-menu-location] [class*="title"],
				body.atx-vb-pin-preview-active [data-atx-vb-menu-location] [class*="label"],
				body.atx-vb-pin-preview-active #atx-nav-menu [class*="title"],
				body.atx-vb-pin-preview-active #atx-nav-menu [class*="label"],
				body.atx-vb-pin-preview-active .atx-nav-brands__item {
					opacity: 1 !important;
					visibility: visible !important;
					transform: none !important;
					filter: none !important;
					clip-path: none !important;
					animation: none !important;
					transition: none !important;
				}
			` : '';

			pinnedIds.forEach(function (id) {
				let item = VB.getItem(parseInt(id, 10));
				if (!item) return;

				let classes = item.classes || [];

				if (item.parent_id === 0) {
					if (classes.includes('atx-nested-nav')) {
						css += `
							.atx-nav-top-item--nested { position: relative; }
							.atx-nav-top-item--nested .atx-nav-mega-dropdown--nested {
								opacity: 1 !important;
								visibility: visible !important;
								transform: none !important;
								position: relative !important;
								width: 100% !important;
							}
							.atx-nav-menu__scroll-area { display: none !important; }
							.atx-nav-menu__cta-fixed { display: none !important; }
						`;
					} else {
						css += `
							.atx-nav-top-item[data-item-id="${id}"] > .atx-nav-mega-dropdown,
							.atx-nav-top-item[data-vb-id="${id}"] > .atx-nav-mega-dropdown,
							.atx-nav-top-item:nth-child(${getItemPosition(item)}) > .atx-nav-mega-dropdown {
								opacity: 1 !important;
								visibility: visible !important;
								transform: translateY(0) scale(1) !important;
							}
						`;
					}
				} else {
					css += `
						.atx-nav-nested-panel { display: block !important; }
						.atx-nav-nested-bar__item--has-panel { }
					`;
				}

				css += `
					[data-item-id="${id}"] {
						z-index: 999 !important;
					}
					[data-item-id="${id}"] > a,
					[data-item-id="${id}"] > [data-item-id] {
						opacity: 1 !important;
					}
					[data-item-id="${id}"] > .nav-dropdown,
					[data-item-id="${id}"] > .child-dropdown,
					[data-item-id="${id}"] > .sub-menu,
					[data-item-id="${id}"] > .children,
					[data-item-id="${id}"] > .dropdown-menu,
					[data-item-id="${id}"] > .mega-menu,
					[data-item-id="${id}"] > [class*="dropdown"],
					[data-item-id="${id}"] > [class*="submenu"],
					[data-item-id="${id}"] > ul {
						display: block !important;
						opacity: 1 !important;
						visibility: visible !important;
						pointer-events: auto !important;
						height: auto !important;
						max-height: none !important;
						overflow: visible !important;
						transform: none !important;
					}
				`;
			});

			if (css) {
				$head.append(`<style id="atx-vb-pin-styles">${css}</style>`);
			}
		} catch (e) {
			// Cross-origin or iframe not ready
		}
	}

	VB.applyPinsToPreview = applyPinsToIframe;

	function getItemPosition(item) {
		let siblings = VB.items.filter(i => i.parent_id === item.parent_id);
		siblings.sort((a, b) => a.position - b.position);
		return siblings.indexOf(item) + 1;
	}

	// Re-apply pins when iframe loads
	$(document).on('load', '#atx-vb-preview', function () {
		setTimeout(applyPinsToIframe, 200);
	});

})(jQuery);
