/**
 * Atx Nav Menu - Flyout Layout
 * Handles hover on sidebar categories to show sub-link panels,
 * and 3-tier cascading brands (parent → category → sub-link).
 */
(function ($) {
	'use strict';

	let N = window.AtxNavMenu;

	N.bindFlyout = function () {
		let self = this;
		let delay = this.config.nestedHoverDelay;
		let flyoutTimer = null;
		let activeSublinkId = null;

		// Category hover: show panel, hide default
		this.$nav.on('mouseenter', '.atx-nav-flyout__cat--has-panel', function () {
			let $cat = $(this);
			clearTimeout(flyoutTimer);
			flyoutTimer = setTimeout(function () {
				$cat.closest('.atx-nav-flyout__cat-list')
					.find('.atx-nav-flyout__cat--active')
					.removeClass('atx-nav-flyout__cat--active');

				$cat.addClass('atx-nav-flyout__cat--active');

				// Reset sub-link brands when switching categories
				activeSublinkId = null;
				let $brandsCol = $cat.find('.atx-nav-flyout__panel-brands');
				resetSublinkBrands($brandsCol);

				// Re-trigger stagger animation on panel brands
				self.restaggerBrands($brandsCol);
			}, delay);
		});

		// Leaving a category: reset to default after delay
		this.$nav.on('mouseleave', '.atx-nav-flyout__cat--has-panel', function () {
			clearTimeout(flyoutTimer);
			flyoutTimer = setTimeout(function () {
				self.$nav.find('.atx-nav-flyout__cat--active')
					.removeClass('atx-nav-flyout__cat--active');
				activeSublinkId = null;
			}, delay);
		});

		// Entering the panel: keep it open
		this.$nav.on('mouseenter', '.atx-nav-flyout__panel', function () {
			clearTimeout(flyoutTimer);
		});

		// Leaving the panel: close after delay
		this.$nav.on('mouseleave', '.atx-nav-flyout__panel', function () {
			clearTimeout(flyoutTimer);
			flyoutTimer = setTimeout(function () {
				self.$nav.find('.atx-nav-flyout__cat--active')
					.removeClass('atx-nav-flyout__cat--active');
				activeSublinkId = null;
			}, delay);
		});

		// Sub-link hover: swap brands if this sub-link has its own
		this.$nav.on('mouseenter', '.atx-nav-flyout__sublink', function () {
			let $sublink = $(this);
			let sublinkId = $sublink.data('item-id');
			let $brands = $sublink.find('.atx-nav-flyout__sublink-brands');

			if (!$brands.length || !$brands.html().trim()) {
				// This sub-link has no brands — reset to category brands
				if (activeSublinkId) {
					activeSublinkId = null;
					let $brandsCol = $sublink.closest('.atx-nav-flyout__panel').find('.atx-nav-flyout__panel-brands');
					resetSublinkBrands($brandsCol);
					self.restaggerBrands($brandsCol);
				}
				return;
			}

			// Same sub-link — keep active, don't re-clone
			if (activeSublinkId === sublinkId) return;

			activeSublinkId = sublinkId;

			let $brandsCol = $sublink.closest('.atx-nav-flyout__panel').find('.atx-nav-flyout__panel-brands');
			if (!$brandsCol.length) return;

			let $slot = $brandsCol.find('.atx-nav-flyout__panel-brands-slot');
			if (!$slot.length) {
				$slot = $('<div class="atx-nav-flyout__panel-brands-slot"></div>');
				$brandsCol.append($slot);
			}

			$slot.html($brands.html()).show();
			$brandsCol.addClass('atx-nav-flyout__panel-brands--sublink-active');

			// Stagger the newly cloned brands
			self.restaggerBrands($slot);
		});

		function resetSublinkBrands($brandsCol) {
			if (!$brandsCol.length) return;
			$brandsCol.removeClass('atx-nav-flyout__panel-brands--sublink-active');
			$brandsCol.find('.atx-nav-flyout__panel-brands-slot').hide().html('');
		}

	};

})(jQuery);
