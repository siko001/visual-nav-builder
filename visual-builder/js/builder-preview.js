/**
 * Visual Builder - Live Website Preview
 * Loads the real front-end page and lets WordPress render the selected menu
 * in the location where the active theme actually uses it.
 */
(function ($) {
	'use strict';

	let VB = window.AtxVB;
	const viewportWidths = {
		desktop: 1440,
		tablet: 768,
		mobile: 390,
	};
	const viewportMinimumScales = {
		desktop: 0.8,
		tablet: 0.8,
		mobile: 0.8,
	};
	let previewViewport = 'desktop';
	let syncRequest = null;
	let standaloneRequest = null;
	let syncSequence = 0;
	let fallbackLocations = {};
	let viewportFrame = 0;

	try {
		const savedViewport = window.localStorage.getItem('atx_vb_preview_viewport');
		if (Object.prototype.hasOwnProperty.call(viewportWidths, savedViewport)) {
			previewViewport = savedViewport;
		}
	} catch (e) {
		// Keep the desktop default if storage is unavailable.
	}

	VB.refreshPreview = function () {
		this.recalcPositions();

		let previewItems = this.items.map(item => ({
			id: item.id,
			title: item.title,
			url: item.url || '#',
			parent_id: item.parent_id || 0,
			position: item.position,
			classes: item.classes || [],
			type: item.type || 'custom',
			object: item.object || 'custom',
			object_id: item.object_id || item.id,
			acf: item.acf || {},
			icon: item.icon || '',
			extras: VB.extras[item.id] || item.extras || {},
			is_new: Boolean(item.is_new),
		}));

		const sequence = ++syncSequence;

		if (syncRequest && syncRequest.readyState !== 4) {
			syncRequest.abort();
		}
		if (standaloneRequest && standaloneRequest.readyState !== 4) {
			standaloneRequest.abort();
		}

		syncRequest = $.ajax({
			url: atxVB.ajaxUrl,
			method: 'POST',
			data: {
				action: 'atx_vb_preview_sync',
				_wpnonce: atxVB.nonce,
				menu_location: VB.menuLocation,
				items: JSON.stringify(previewItems),
			},
			success: function (res) {
				if (sequence !== syncSequence) return;

				if (!res || !res.success) {
					setPreviewContext('Could not update the website preview.', 'missing');
					return;
				}

				loadWebsitePreview();
			},
			error: function (xhr, status) {
				if (status !== 'abort' && sequence === syncSequence) {
					setPreviewContext('Could not update the website preview.', 'missing');
				}
			}
		});
	};

	function loadWebsitePreview() {
		if (fallbackLocations[VB.menuLocation]) {
			loadStandalonePreview();
			return;
		}

		const baseUrl = atxVB.previewUrls && atxVB.previewUrls[VB.menuLocation];
		if (!baseUrl) {
			setPreviewContext('No preview page is configured for this location.', 'missing');
			return;
		}

		const url = new URL(baseUrl, window.location.href);
		url.searchParams.set('_atx_vb_refresh', String(Date.now()));

		const iframe = document.getElementById('atx-vb-preview');
		iframe.removeAttribute('srcdoc');
		iframe.src = url.toString();
	}

	function loadStandalonePreview() {
		const location = VB.menuLocation;
		const sequence = syncSequence;
		setPreviewContext('Primary V2 is not rendered by this theme · showing standalone preview', 'ready');

		standaloneRequest = $.ajax({
			url: atxVB.ajaxUrl,
			method: 'POST',
			dataType: 'html',
			data: {
				action: atxVB.previewAction || 'atx_vb_preview_page',
				_wpnonce: atxVB.nonce,
				menu_location: VB.menuLocation,
				_: Date.now(),
			},
			success: function (html) {
				if (location !== VB.menuLocation || sequence !== syncSequence) return;
				const iframe = document.getElementById('atx-vb-preview');
				iframe.removeAttribute('src');
				iframe.srcdoc = html;
			},
			error: function (xhr, status) {
				if (status === 'abort' || location !== VB.menuLocation || sequence !== syncSequence) return;
				const message = xhr && xhr.responseText ? xhr.responseText : 'Standalone preview failed to load.';
				const iframe = document.getElementById('atx-vb-preview');
				iframe.removeAttribute('src');
				iframe.srcdoc = '<!doctype html><body style="font-family:sans-serif;padding:16px;">' + escapeHtml(message) + '</body>';
				setPreviewContext('Could not load the standalone Primary V2 preview.', 'missing');
			}
		});
	}

	function escapeHtml(value) {
		let div = document.createElement('div');
		div.textContent = value || '';
		return div.innerHTML;
	}

	function setPreviewContext(message, state) {
		$('#atx-vb-preview-context')
			.removeClass('atx-vb__preview-context--ready atx-vb__preview-context--missing')
			.addClass(state ? 'atx-vb__preview-context--' + state : '')
			.text(message || '');
	}

	/**
	 * Intercept clicks inside the selected theme location. Navigation is
	 * disabled; clicking a menu item selects it in the builder instead.
	 */
	function bindIframeClicks() {
		try {
			let iframe = document.getElementById('atx-vb-preview');
			let iframeDoc = iframe.contentDocument || iframe.contentWindow.document;

			$(iframeDoc).off('click.atxvb').on(
				'click.atxvb',
				'[data-atx-vb-menu-location] a, [data-atx-vb-menu-location] [data-item-id], #atx-nav-menu a, #atx-nav-menu [data-item-id]',
				function (e) {
					e.preventDefault();
					e.stopPropagation();

					let $el = $(this).closest('[data-item-id]');
					let itemId = $el.length ? parseInt($el.data('item-id'), 10) : 0;

					if (itemId) {
						let match = VB.getItem(itemId);
						if (match) {
							selectItemInTree(match);
						}
					}
				}
			);

			$(iframeDoc).off('click.atxvb-extras').on('click.atxvb-extras', '.atx-nav-slider, .atx-nav-brands', function (e) {
				e.preventDefault();
				e.stopPropagation();

				let $parent = $(this).closest('[data-item-id]');
				if ($parent.length) {
					let match = VB.getItem(parseInt($parent.data('item-id'), 10));
					if (match) selectItemInTree(match);
				}
			});
		} catch (e) {
			setPreviewContext('The preview page blocked builder interaction.', 'missing');
		}
	}

	function focusThemeLocation() {
		try {
			const iframe = document.getElementById('atx-vb-preview');
			if (iframe.contentWindow && iframe.contentWindow.AtxVBPreview) {
				iframe.contentWindow.AtxVBPreview.focusLocation();
			}
		} catch (e) {
			// The status bridge will report cross-origin or frame failures.
		}
	}

	/**
	 * Select an item in the tree and open its editor.
	 */
	function selectItemInTree(item) {
		let parentId = item.parent_id;
		while (parentId) {
			let $parentEl = $(`.atx-vb-item[data-id="${parentId}"]`);
			if ($parentEl.length && $parentEl.find('.atx-vb-item__toggle').text().trim() === '►') {
				$parentEl.find('.atx-vb-item__toggle').trigger('click');
			}
			let parent = VB.getItem(parentId);
			parentId = parent ? parent.parent_id : 0;
		}

		VB.selectedId = item.id;
		VB.openEditor(item);
		$('.atx-vb-item--active').removeClass('atx-vb-item--active');
		$(`.atx-vb-item[data-id="${item.id}"]`).addClass('atx-vb-item--active');

		let $el = $(`.atx-vb-item[data-id="${item.id}"]`);
		if ($el.length) {
			$el[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
		}
	}

	window.addEventListener('message', function (event) {
		if (event.origin !== window.location.origin || !event.data || event.data.type !== 'atx-vb-preview-location') {
			return;
		}

		if (event.data.location !== VB.menuLocation) {
			return;
		}
	});

	$('#atx-vb-preview').on('load', function () {
		setTimeout(function () {
			applyViewport();
			bindIframeClicks();
			focusThemeLocation();
		}, 150);
	});

	$('#atx-vb-refresh-preview').on('click', function () {
		VB.refreshPreview();
	});

	$('.atx-vb__viewport-btn').on('click', function () {
		previewViewport = $(this).data('viewport') || 'desktop';

		try {
			window.localStorage.setItem('atx_vb_preview_viewport', previewViewport);
		} catch (e) {
			// The selected viewport still applies for this page load.
		}

		applyViewport();
		setTimeout(focusThemeLocation, 250);
	});

	function applyViewport() {
		const stage = document.getElementById('atx-vb-preview-stage');
		const frame = document.getElementById('atx-vb-preview-frame');
		const iframe = document.getElementById('atx-vb-preview');
		if (!stage || !frame || !iframe) return;

		$('.atx-vb__viewport-btn')
			.removeClass('atx-vb__viewport-btn--active')
			.filter('[data-viewport="' + previewViewport + '"]')
			.addClass('atx-vb__viewport-btn--active');

		$(stage)
			.removeClass('atx-vb__preview-stage--desktop atx-vb__preview-stage--tablet atx-vb__preview-stage--mobile')
			.addClass('atx-vb__preview-stage--' + previewViewport);

		const computed = window.getComputedStyle(stage);
		const horizontalPadding = parseFloat(computed.paddingLeft || 0) + parseFloat(computed.paddingRight || 0);
		const verticalPadding = parseFloat(computed.paddingTop || 0) + parseFloat(computed.paddingBottom || 0);
		const availableWidth = Math.max(1, stage.clientWidth - horizontalPadding);
		const availableHeight = Math.max(1, stage.clientHeight - verticalPadding);
		const targetWidth = viewportWidths[previewViewport] || viewportWidths.desktop;
		const minimumScale = viewportMinimumScales[previewViewport] || 0.8;
		const scale = Math.max(minimumScale, Math.min(1, availableWidth / targetWidth));
		const scaledWidth = Math.floor(targetWidth * scale);

		stage.style.justifyContent = scaledWidth > availableWidth ? 'flex-start' : 'center';
		frame.style.width = scaledWidth + 'px';
		frame.style.height = availableHeight + 'px';
		iframe.style.width = targetWidth + 'px';
		iframe.style.height = Math.ceil(availableHeight / scale) + 'px';
		iframe.style.transform = 'scale(' + scale + ')';

		stage.setAttribute('data-preview-width', String(targetWidth));
		stage.setAttribute('data-preview-scale', scale.toFixed(4));
		$('#atx-vb-preview-scale')
			.text(Math.round(scale * 100) + '%')
			.attr('title', previewViewport.charAt(0).toUpperCase() + previewViewport.slice(1) + ' viewport: ' + targetWidth + 'px wide, scaled to fit');
	}

	function scheduleViewport() {
		if (viewportFrame) return;
		viewportFrame = window.requestAnimationFrame(function () {
			viewportFrame = 0;
			applyViewport();
		});
	}

	applyViewport();
	window.addEventListener('resize', scheduleViewport);

	if (window.ResizeObserver) {
		const previewResizeObserver = new ResizeObserver(scheduleViewport);
		previewResizeObserver.observe(document.getElementById('atx-vb-preview-stage'));
	}

})(jQuery);
