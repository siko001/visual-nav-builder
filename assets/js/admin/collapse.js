/**
 * Atx Nav Menu Admin - Collapsible Menu Items
 * Adds collapse/expand toggles to menu items that have children.
 */
(function ($) {
	'use strict';

	function init() {
		let $menuItems = $('#menu-to-edit > .menu-item');

		$menuItems.each(function () {
			let $item = $(this);
			let itemId = $item.attr('id');

			if (!itemId) return;

			// Find children (items indented under this one)
			let $children = getChildren($item);

			if ($children.length === 0) {
				$item.removeClass('atx-collapsed');
				$item.find('.atx-collapse-btn').remove();
				return;
			}

			// Add collapse button if not already there
			let $existing = $item.find('.atx-collapse-btn');
			if ($existing.length) {
				$existing.html(`${$item.hasClass('atx-collapsed') ? '►' : '▼'} ${$children.length}`);
				return;
			}

			let $handle = $item.find('.menu-item-handle');
			let $btn = $(`<button type="button" class="atx-collapse-btn" title="Collapse/Expand children" style="
				position:absolute;
				left:50%;
				top:50%;
				transform:translate(-50%, -50%);
				background:#f0f0f0;
				border:1px solid #ddd;
				border-radius:3px;
				padding:2px 8px;
				font-size:11px;
				cursor:pointer;
				z-index:1;
				color:#666;
			">▼ ${$children.length}</button>`);

			$handle.css('position', 'relative');
			$handle.append($btn);

			$btn.on('click', function (e) {
				e.preventDefault();
				e.stopPropagation();

				let $kids = getChildren($item);
				let isCollapsed = $item.hasClass('atx-collapsed');

				if (isCollapsed) {
					$kids.slideDown(200);
					$item.removeClass('atx-collapsed');
					$(this).html(`▼ ${$kids.length}`);
				} else {
					$kids.slideUp(200);
					$item.addClass('atx-collapsed');
					$(this).html(`► ${$kids.length}`);
				}
			});
		});

		updateToolbarVisibility();
	}

	/**
	 * Get all direct and nested children of a menu item
	 */
	function getChildren($item) {
		let $children = $();
		let baseDepth = getDepth($item);
		let $next = $item.next('.menu-item');

		while ($next.length) {
			let nextDepth = getDepth($next);
			if (nextDepth <= baseDepth) break;
			$children = $children.add($next);
			$next = $next.next('.menu-item');
		}

		return $children;
	}

	/**
	 * Get the depth of a menu item based on its CSS class
	 */
	function getDepth($item) {
		let classes = $item.attr('class') || '';
		let match = classes.match(/menu-item-depth-(\d+)/);
		return match ? parseInt(match[1], 10) : 0;
	}

	function collapseAll() {
		$('#menu-to-edit > .menu-item').each(function () {
			let $item = $(this);
			let $children = getChildren($item);
			if ($children.length && !$item.hasClass('atx-collapsed')) {
				$children.slideUp(150);
				$item.addClass('atx-collapsed');
				$item.find('.atx-collapse-btn').html(`► ${$children.length}`);
			}
		});
	}

	function expandAll() {
		$('#menu-to-edit > .menu-item').each(function () {
			let $item = $(this);
			let $children = getChildren($item);
			if ($children.length && $item.hasClass('atx-collapsed')) {
				$children.slideDown(150);
				$item.removeClass('atx-collapsed');
				$item.find('.atx-collapse-btn').html(`▼ ${$children.length}`);
			}
		});
	}

	function updateToolbarVisibility() {
		let hasHierarchy = $('#menu-to-edit > .menu-item').filter(function () {
			return getDepth($(this)) > 0;
		}).length > 0;

		$('#atx-collapse-toolbar').css('display', hasHierarchy ? 'flex' : 'none');
	}

	function refreshControls() {
		window.requestAnimationFrame(function () {
			init();
			updateToolbarVisibility();
		});
	}

	// Init on page load and after menu save/reorder
	$(document).ready(function () {
		setTimeout(function () {
			// Add Collapse All / Expand All buttons
			let $toolbar = $('<div id="atx-collapse-toolbar" style="margin:8px 0;display:none;gap:6px;"></div>');
			$toolbar.append('<button type="button" class="button button-small" id="atx-collapse-all">Collapse All</button>');
			$toolbar.append('<button type="button" class="button button-small" id="atx-expand-all">Expand All</button>');
			$('#menu-to-edit').before($toolbar);

			$('#atx-collapse-all').on('click', collapseAll);
			$('#atx-expand-all').on('click', expandAll);
			init();
		}, 500);

		$(document).on('menu-item-added', refreshControls);
		$('#menu-to-edit').on('sortstop', refreshControls);
		$(document).on('click', '#menu-to-edit .item-delete', function () {
			window.setTimeout(refreshControls, 250);
		});
		$(document).ajaxComplete(function (e, xhr, settings) {
			if (settings.data && settings.data.indexOf('action=menu-locations-save') !== -1) {
				setTimeout(refreshControls, 500);
			}
		});
	});

})(jQuery);
