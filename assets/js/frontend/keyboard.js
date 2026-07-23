/**
 * Atx Nav Menu - Keyboard Accessibility
 */
(function ($) {
	'use strict';

	let N = window.AtxNavMenu;

	N.bindKeyboard = function () {
		let self = this;

		$(document).on('keydown', function (e) {
			if (e.key === 'Escape') {
				self.closeAllDropdowns();
			}
		});

		this.$nav.find('.atx-nav-top-link').on('focus', function () {
			let $item = $(this).closest('.atx-nav-top-item--has-children');
			if ($item.length) {
				self.openTopItem($item);
			}
		});

		this.$nav.find('.atx-nav-nested-bar__link').on('keydown', function (e) {
			let $current = $(this).closest('.atx-nav-nested-bar__item');
			let $target;

			if (e.key === 'ArrowRight') {
				$target = $current.next('.atx-nav-nested-bar__item');
			} else if (e.key === 'ArrowLeft') {
				$target = $current.prev('.atx-nav-nested-bar__item');
			}

			if ($target && $target.length) {
				e.preventDefault();
				$target.find('.atx-nav-nested-bar__link').focus();
				if ($target.hasClass('atx-nav-nested-bar__item--has-panel')) {
					self.activateNestedTab($target);
				}
			}
		});
	};

})(jQuery);
