/**
 * Atx Nav Menu Admin - Visual Preview (Live, no save required)
 * Scrapes the current menu editor state and renders a live preview.
 */
(function ($) {
	'use strict';

	let $modal = null;

	/**
	 * Scrape the current menu structure from the WordPress menu editor DOM
	 */
	function scrapeMenuFromDOM() {
		let items = [];
		let depthParentStack = [0]; // Track parent IDs by depth

		$('#menu-to-edit > .menu-item').each(function () {
			let $item = $(this);
			let id = $item.attr('id').replace('menu-item-', '');
			let depth = 0;
			let classes = $item.attr('class') || '';
			let depthMatch = classes.match(/menu-item-depth-(\d+)/);
			if (depthMatch) depth = parseInt(depthMatch[1], 10);

			// Get field values from the settings panel
			let title = $item.find('.edit-menu-item-title').val() || $item.find('.menu-item-title').text().trim();
			let url = $item.find('.edit-menu-item-url').val() || '#';
			let cssClasses = $item.find('.edit-menu-item-classes').val() || '';

			// Get icon from our custom select
			let icon = $item.find('.atx-nav-icon-select').val() || '';

			// Determine parent
			depthParentStack[depth] = parseInt(id, 10);
			let parentId = depth > 0 ? (depthParentStack[depth - 1] || 0) : 0;

			items.push({
				id: parseInt(id, 10),
				title: title,
				url: url,
				parent_id: parentId,
				position: items.length + 1,
				classes: cssClasses.split(/\s+/).filter(Boolean),
				icon: icon,
				depth: depth,
			});
		});

		return items;
	}

	function openPreview() {
		if ($modal) closePreview();

		let html = atxNavAdmin.template('atx-tmpl-preview-modal', {
			previewUrl: 'about:blank'
		});

		$modal = $(html).appendTo('body');
		$('body').css('overflow', 'hidden');

		// Show loading state
		$modal.find('iframe').css('background', '#f5f5f5');

		// Scrape menu and send to server
		let items = scrapeMenuFromDOM();

		$.ajax({
			url: ajaxurl,
			method: 'POST',
			data: {
				action: 'atx_nav_preview_save',
				_wpnonce: atxNavAdmin.nonce,
				items: JSON.stringify(items),
			},
			success: function (response) {
				if (response.success && $modal) {
					let previewUrl = atxNavPreview.siteUrl + '?nav=v2&preview_live=1&_=' + Date.now();
					$modal.find('iframe').attr('src', previewUrl);
					$modal.find('a[target="_blank"]').attr('href', previewUrl);
				}
			}
		});
	}

	function closePreview() {
		if ($modal) {
			$modal.remove();
			$modal = null;
			$('body').css('overflow', '');
		}
	}

	// Close
	$(document).on('click', '.atx-preview-close', closePreview);
	$(document).on('keydown', function (e) {
		if (e.key === 'Escape' && $modal) closePreview();
	});

	// Responsive sizes
	$(document).on('click', '.atx-preview-size', function () {
		let width = $(this).data('width');
		$modal.find('.atx-preview-frame-wrap').css('width', width);
		$modal.find('.atx-preview-size').css({ background: '#333' });
		$(this).css({ background: '#555' });
	});

	// Refresh — re-scrape and reload
	$(document).on('click', '.atx-preview-refresh', function () {
		let items = scrapeMenuFromDOM();
		let $iframe = $modal.find('iframe');

		$.ajax({
			url: ajaxurl,
			method: 'POST',
			data: {
				action: 'atx_nav_preview_save',
				_wpnonce: atxNavAdmin.nonce,
				items: JSON.stringify(items),
			},
			success: function () {
				$iframe.attr('src', atxNavPreview.siteUrl + '?nav=v2&preview_live=1&_=' + Date.now());
			}
		});
	});

	// Inject button
	$(document).ready(function () {
		if (typeof atxNavPreview === 'undefined') return;

		let $btn = $('<button type="button" class="button" style="margin-left:8px;">Preview Nav</button>');
		$btn.on('click', openPreview);

		let $target = $('#nav-menu-header .menu-name-label');
		if ($target.length) {
			$target.after($btn);
		}
	});

})(jQuery);
