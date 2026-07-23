/**
 * Atx Nav Menu Admin - Slide Management
 */
(function ($) {
	'use strict';

	// Upload slide image
	$(document).on('click', '.atx-slide-upload-btn', function (e) {
		e.preventDefault();
		let $row = $(this).closest('.atx-nav-slide-row');
		window.atxOpenImagePicker(function (id, url) {
			$row.find('.atx-slide-image-id').val(id);
			$row.find('.atx-slide-preview').html(
				`<img src="${url}" style="width:100%;height:100%;object-fit:cover;" />`
			);
		});
	});

	// Add slide
	$(document).on('click', '.atx-add-slide-btn', function () {
		let itemId = $(this).data('item-id');
		let $container = $(this).siblings('.atx-nav-slider-items');
		let index = $container.children().length;

		let html = atxNavAdmin.template('atx-tmpl-slide-row', { itemId, index });
		$container.append(html);
	});

	// Remove slide
	$(document).on('click', '.atx-slide-remove-btn', function () {
		$(this).closest('.atx-nav-slide-row').remove();
	});

})(jQuery);
