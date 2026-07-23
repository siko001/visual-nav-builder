/**
 * Atx Nav Menu - Top Level Interactions
 * Hover intent for standard dropdowns, click for nested items.
 * Supports multiple secondary (nested) nav items.
 */
(function ($) {
	'use strict';

	let N = window.AtxNavMenu;

	// Track which nested item currently has its dropdown detached
	N._activeNestedItem = null;

	N.bindTopLevel = function () {
		let self = this;
		let $hoverItems = this.$nav.find('.atx-nav-top-item--has-children:not(.atx-nav-top-item--nested)');
		let $clickItems = this.$nav.find('.atx-nav-top-item--nested');

		$hoverItems.on('mouseenter', function () {
			let $item = $(this);
			clearTimeout(self.hoverOutTimer);
			clearTimeout(self.hoverTimer);
			self.hoverTimer = setTimeout(function () {
				self.openTopItem($item);
			}, self.config.hoverDelay);
		});

		$hoverItems.on('mouseleave', function () {
			clearTimeout(self.hoverTimer);
			self.hoverOutTimer = setTimeout(function () {
				self.closeAllDropdowns();
			}, self.config.hoverOutDelay);
		});

		$clickItems.find('> .atx-nav-top-link').on('click', function (e) {
			e.preventDefault();
			let $item = $(this).closest('.atx-nav-top-item');
			if ($item.hasClass('atx-nav-top-item--active')) {
				self.closeAllDropdowns();
			} else {
				self.closeAllDropdowns();
				self.openTopItem($item);
			}
		});

		$clickItems.on('mouseenter', function () {
			clearTimeout(self.hoverOutTimer);
			clearTimeout(self.hoverTimer);
		});

		$clickItems.on('mouseleave', function () {
			clearTimeout(self.hoverTimer);
		});

		this.$nav.find('.atx-nav-mega-dropdown').on('mouseenter', function () {
			clearTimeout(self.hoverOutTimer);
		});

		this.$nav.find('.atx-nav-mega-dropdown:not(.atx-nav-mega-dropdown--nested)').on('mouseleave', function () {
			self.hoverOutTimer = setTimeout(function () {
				self.closeAllDropdowns();
			}, self.config.hoverOutDelay);
		});
	};

	N.openTopItem = function ($item) {
		if (this.activeTopItem && this.activeTopItem[0] === $item[0]) return;

		// Close previous — this also restores any detached nested dropdown
		if (this.activeTopItem) {
			this.restoreNestedDropdown();
			this.activeTopItem.removeClass('atx-nav-top-item--active');
			this.activeTopItem.find('.atx-nav-mega-dropdown').css('transition', 'none');
			this.activeTopItem.find('.atx-nav-mega-dropdown')[0] && this.activeTopItem.find('.atx-nav-mega-dropdown')[0].offsetHeight;
			this.activeTopItem.find('.atx-nav-mega-dropdown').css('transition', '');
		}

		this.$nav.find('.atx-nav-nested-bar__item--active').removeClass('atx-nav-nested-bar__item--active');
		this.activeNestedItem = null;
		this.destroyAllSwipers();

		$item.addClass('atx-nav-top-item--active');
		this.activeTopItem = $item;

		// If nested nav, detach dropdown and show it in place of the main bar
		if ($item.hasClass('atx-nav-top-item--nested')) {
			let $dropdown = $item.find('.atx-nav-mega-dropdown--nested');
			if ($dropdown.length) {
				$dropdown.detach().appendTo(this.$nav.find('.atx-nav-menu__container'));
				this._activeNestedItem = $item;
				this.$nav.find('.atx-nav-menu__scroll-area').hide();
				this.$nav.find('.atx-nav-menu__cta-fixed').hide();
			}
		}

		// Don't auto-activate nested tabs — let user hover to open them

		this.initSlidersIn($item);

		// Re-trigger brand stagger animation
		this.restaggerBrands($item.find('.atx-nav-mega-dropdown'));
	};

	/**
	 * Restore the currently detached nested dropdown back to its parent item
	 */
	N.restoreNestedDropdown = function () {
		if (!this._activeNestedItem) return;

		let $dropdown = this.$nav.find('.atx-nav-menu__container > .atx-nav-mega-dropdown--nested');
		if ($dropdown.length && this._activeNestedItem.length) {
			$dropdown.detach().appendTo(this._activeNestedItem);
		}

		this._activeNestedItem = null;
		this.$nav.find('.atx-nav-menu__scroll-area').show();
		this.$nav.find('.atx-nav-menu__cta-fixed').show();
	};

	N.closeAllDropdowns = function () {
		if (this.activeTopItem && this.activeTopItem.hasClass('atx-nav-top-item--nested')) {
			this.nestedLocked = true;
		}

		// Restore any detached nested dropdown
		this.restoreNestedDropdown();

		this.$nav.find('.atx-nav-top-item--active').removeClass('atx-nav-top-item--active');
		this.$nav.find('.atx-nav-nested-bar__item--active').removeClass('atx-nav-nested-bar__item--active');
		this.$nav.find('.atx-nav-menu__scroll-area').show();
		this.$nav.find('.atx-nav-menu__cta-fixed').show();
		this.activeTopItem = null;
		this.activeNestedItem = null;
		this.destroyAllSwipers();
	};

})(jQuery);
