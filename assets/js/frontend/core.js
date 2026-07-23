/**
 * Atx Nav Menu - Core
 * Creates the namespace, config, state, and bootstraps on DOM ready.
 */
(function ($) {
	'use strict';

	// Config from PHP (with fallback defaults)
	let cfg = window.atxNavMenu || {};

	window.AtxNavMenu = {
		config: {
			hoverDelay: parseInt(cfg.hoverDelay, 10) || 200,
			hoverOutDelay: parseInt(cfg.hoverOutDelay, 10) || 300,
			sliderInterval: parseInt(cfg.sliderInterval, 10) || 5000,
			nestedHoverDelay: parseInt(cfg.nestedHoverDelay, 10) || 150,
		},

		hoverTimer: null,
		hoverOutTimer: null,
		swiperInstances: [],
		activeTopItem: null,
		activeNestedItem: null,
		nestedLocked: false,

		init: function () {
			this.$nav = $('#atx-nav-menu');
			if (!this.$nav.length) return;

			this.extractCTA();
			this.bindTopLevel();
			this.bindNestedNav();
			this.bindFlyout();
			this.bindSliders();
			this.bindKeyboard();
		},

		// Re-trigger CSS stagger animation on brand items by replacing DOM nodes
		restaggerBrands: function ($container) {
			if (!$container || !$container.length) return;
			$container.find('.atx-nav-brands__item').each(function () {
				let clone = this.cloneNode(true);
				this.parentNode.replaceChild(clone, this);
			});
		},

		extractCTA: function () {
			let $cta = this.$nav.find('.atx-nav-top-item--cta');
			let $ctaContainer = this.$nav.find('.atx-nav-menu__cta-fixed');

			if ($cta.length && $ctaContainer.length) {
				$cta.detach().appendTo($ctaContainer);
			}
		},
	};

	$(document).ready(function () {
		window.AtxNavMenu.init();
	});

})(jQuery);
