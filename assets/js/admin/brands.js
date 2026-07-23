/**
 * Atx Nav Menu Admin - Brand Management
 */
(function ($) {
	'use strict';

	// Upload brand logo
	$(document).on('click', '.atx-brand-upload-btn', function (e) {
		e.preventDefault();
		let $row = $(this).closest('.atx-nav-brand-row');
		window.atxOpenImagePicker(function (id, url) {
			$row.find('.atx-brand-logo-id').val(id);
			$row.find('.atx-brand-preview').html(
				`<img src="${url}" style="width:100%;height:100%;object-fit:contain;" />`
			);
		});
	});

	// Add brand
	$(document).on('click', '.atx-add-brand-btn', function () {
		let itemId = $(this).data('item-id');
		let $container = $(this).siblings('.atx-nav-brand-items');
		let index = $container.children().length;

		let html = atxNavAdmin.template('atx-tmpl-brand-row', { itemId, index });
		$container.append(html);
	});

	// Remove brand
	$(document).on('click', '.atx-brand-remove-btn', function () {
		$(this).closest('.atx-nav-brand-row').remove();
	});

})(jQuery);
