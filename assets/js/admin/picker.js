/**
 * Atx Nav Menu Admin - Image Picker
 * Custom AJAX-powered inline image picker. No wp.media() dependency.
 */
(function ($) {
	'use strict';

	let $activePicker = null;
	let pickerCallback = null;
	let searchTimer = null;
	let currentPage = 1;

	function openImagePicker(callback) {
		closePicker();
		pickerCallback = callback;

		let html = atxNavAdmin.template('atx-tmpl-image-picker');
		$activePicker = $(html).appendTo('body');
		loadImages('', 1);
		$('body').css('overflow', 'hidden');
	}

	function closePicker() {
		if ($activePicker) {
			$activePicker.remove();
			$activePicker = null;
			pickerCallback = null;
			$('body').css('overflow', '');
		}
	}

	function loadImages(search, page) {
		let $grid = $activePicker.find('.atx-image-picker__grid');
		let $loadMore = $activePicker.find('.atx-image-picker__loadmore');

		if (page === 1) {
			$grid.html('<p style="grid-column:1/-1;text-align:center;color:#999;">Loading...</p>');
		}

		$.ajax({
			url: ajaxurl,
			method: 'POST',
			data: {
				action: 'atx_nav_get_images',
				search: search,
				page: page,
				_wpnonce: atxNavAdmin.nonce
			},
			success: function (response) {
				if (!$activePicker) return;
				if (page === 1) $grid.html('');

				if (response.data && response.data.images && response.data.images.length) {
					response.data.images.forEach(function (img) {
						let $item = $(`
							<div class="atx-image-picker__item" data-id="${img.id}" data-url="${img.url}"
								style="cursor:pointer;border:2px solid transparent;border-radius:4px;overflow:hidden;aspect-ratio:1;background:#f5f5f5;">
								<img src="${img.thumb}" style="width:100%;height:100%;object-fit:cover;" title="${img.title || ''}" />
							</div>`);
						$grid.append($item);
					});

					currentPage = page;
					$loadMore.toggle(response.data.has_more);

					if (page > 1) {
						$grid[0].scrollTop = $grid[0].scrollHeight;
					}
				} else if (page === 1) {
					$grid.html('<p style="grid-column:1/-1;text-align:center;color:#999;">No images found.</p>');
					$loadMore.hide();
				}
			}
		});
	}

	// Expose globally for icon picker
	window.atxOpenImagePicker = function (callback) { openImagePicker(callback); };

	// Close picker
	$(document).on('click', '.atx-image-picker__close, .atx-image-picker__backdrop', closePicker);

	// Select image
	$(document).on('click', '.atx-image-picker__item', function () {
		let id = $(this).data('id');
		let url = $(this).data('url');
		if (pickerCallback) pickerCallback(id, url);
		closePicker();
	});

	// Search
	$(document).on('input', '.atx-image-picker__search', function () {
		let val = $(this).val();
		clearTimeout(searchTimer);
		searchTimer = setTimeout(() => loadImages(val, 1), 300);
	});

	// Load more
	$(document).on('click', '.atx-image-picker__more-btn', function () {
		let search = $activePicker.find('.atx-image-picker__search').val();
		loadImages(search, currentPage + 1);
	});

	// ESC to close
	$(document).on('keydown', function (e) {
		if (e.key === 'Escape') closePicker();
	});

	// File upload
	$(document).on('change', '.atx-image-picker__upload', function () {
		let file = this.files[0];
		if (!file) return;

		let $picker = $(this).closest('.atx-image-picker');
		let $progress = $picker.find('.atx-image-picker__upload-progress');
		let $text = $progress.find('.atx-image-picker__upload-text');
		$progress.show();
		$text.text(`Uploading "${file.name}"...`);

		let formData = new FormData();
		formData.append('action', 'atx_nav_upload_image');
		formData.append('_wpnonce', atxNavAdmin.nonce);
		formData.append('file', file);

		$.ajax({
			url: ajaxurl,
			method: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			success: function (response) {
				$progress.hide();
				if (response.success && response.data) {
					if (pickerCallback) pickerCallback(response.data.id, response.data.url);
					closePicker();
				} else {
					$text.text(`Upload failed: ${response.data || 'Unknown error'}`);
					$progress.show();
					setTimeout(() => $progress.hide(), 3000);
				}
			},
			error: function () {
				$text.text('Upload failed. Please try again.');
				setTimeout(() => $progress.hide(), 3000);
			}
		});

		$(this).val('');
	});

})(jQuery);
