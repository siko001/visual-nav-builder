/**
 * Atx Nav Menu - Nested Navigation
 * Secondary bar tab switching for Built-In Appliances style nav.
 */
(function ($) {
	'use strict';

	let N = window.AtxNavMenu;

	N.bindNestedNav = function () {
		let self = this;
		let $nestedItems = this.$nav.find('.atx-nav-nested-bar__item--has-panel');

		$nestedItems.on('mouseenter', function () {
			let $item = $(this);
			clearTimeout(self.nestedTimer);
			clearTimeout(self.nestedCloseTimer);
			self.nestedTimer = setTimeout(function () {
				self.activateNestedTab($item);
			}, self.config.nestedHoverDelay);
		});

		this.$nav.find('.atx-nav-nested-bar__item:not(.atx-nav-nested-bar__item--has-panel):not(.atx-nav-nested-bar__item--back):not(.atx-nav-nested-bar__item--label)').on('mouseenter', function () {
			clearTimeout(self.nestedTimer);
			self.$nav.find('.atx-nav-nested-bar__item--active').removeClass('atx-nav-nested-bar__item--active');
			self.activeNestedItem = null;
		});

		this.$nav.on('mouseleave', '.atx-nav-mega-dropdown--nested', function () {
			self.nestedCloseTimer = setTimeout(function () {
				self.$nav.find('.atx-nav-nested-bar__item--active').removeClass('atx-nav-nested-bar__item--active');
				self.activeNestedItem = null;
			}, 200);
		});

		this.$nav.on('mouseenter', '.atx-nav-nested-panel', function () {
			clearTimeout(self.nestedCloseTimer);
		});

		this.$nav.find('.atx-nav-nested-bar__item--back a').on('click', function (e) {
			e.preventDefault();
			self.closeAllDropdowns();
		});
	};

	N.activateNestedTab = function ($item) {
		if (this.activeNestedItem && this.activeNestedItem[0] === $item[0]) return;

		$item.closest('.atx-nav-nested-bar__list')
			.find('.atx-nav-nested-bar__item--active')
			.removeClass('atx-nav-nested-bar__item--active');

		$item.addClass('atx-nav-nested-bar__item--active');
		this.activeNestedItem = $item;

		let $panel = $item.find('.atx-nav-nested-panel');
		if ($panel.length) {
			this.initSlidersIn($panel);
			this.restaggerBrands($panel);
		}
	};

})(jQuery);
