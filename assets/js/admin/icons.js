/**
 * Atx Nav Menu Admin - Icon Picker
 * Handles icon select dropdown, custom upload, and preview.
 */
(function ($) {
	'use strict';

	let atxIcons = window.atxNavIcons || {};

	// Icon select change
	$(document).on('change', '.atx-nav-icon-select', function () {
		let $wrap = $(this).closest('.field-atx-icon');
		let val = $(this).val();
		let $preview = $wrap.find('.atx-nav-icon-preview');
		let $customWrap = $wrap.find('.atx-nav-icon-custom-wrap');

		if (val === 'custom') {
			$customWrap.show();
			let customUrl = $wrap.find('.atx-nav-icon-custom-preview img').attr('src');
			$preview.html(customUrl ? `<img src="${customUrl}" />` : '');
		} else {
			$customWrap.hide();
			if (val && atxIcons[val]) {
				$preview.html(atxIcons[val].svg);
			} else {
				$preview.html('');
			}
		}
	});

	// Custom icon upload via image picker
	$(document).on('click', '.atx-nav-icon-upload', function (e) {
		e.preventDefault();
		let $wrap = $(this).closest('.field-atx-icon');
		let $input = $wrap.find('.atx-nav-icon-custom-id');
		let $preview = $wrap.find('.atx-nav-icon-custom-preview');
		let $iconPreview = $wrap.find('.atx-nav-icon-preview');
		let $removeBtn = $wrap.find('.atx-nav-icon-remove');

		if (typeof window.atxOpenImagePicker === 'function') {
			window.atxOpenImagePicker(function (id, url) {
				$input.val(id);
				$preview.html(`<img src="${url}" />`);
				$iconPreview.html(`<img src="${url}" />`);
				$removeBtn.show();
			});
		}
	});

	// Remove custom icon
	$(document).on('click', '.atx-nav-icon-remove', function (e) {
		e.preventDefault();
		let $wrap = $(this).closest('.field-atx-icon');
		$wrap.find('.atx-nav-icon-custom-id').val('');
		$wrap.find('.atx-nav-icon-custom-preview').html('');
		$wrap.find('.atx-nav-icon-preview').html('');
		$(this).hide();
	});

})(jQuery);
