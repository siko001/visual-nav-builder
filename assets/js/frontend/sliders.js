/**
 * Atx Nav Menu - Sliders
 * Swiper initialization and teardown.
 */
(function ($) {
	'use strict';

	let N = window.AtxNavMenu;

	N.bindSliders = function () {
		this.$nav.on('click', '.atx-nav-slider__slide', function () {
			let link = $(this).data('link');
			if (link) {
				window.location.href = link;
			}
		});
	};

	N.initSlidersIn = function ($dropdown) {
		let self = this;

		setTimeout(function () {
			$dropdown.find('[data-atx-slider]').each(function () {
				let el = this;
				if (el.swiper) return;

				let swiper = new Swiper(el, {
					loop: true,
					autoplay: {
						delay: self.config.sliderInterval,
						disableOnInteraction: false,
						pauseOnMouseEnter: true,
					},
					speed: 500,
					grabCursor: true,
					effect: 'slide',
					observer: true,
					observeParents: true,
					pagination: {
						el: $(el).find('.swiper-pagination')[0],
						clickable: true,
					},
				});

				self.swiperInstances.push(swiper);
			});
		}, 50);
	};

	N.destroyAllSwipers = function () {
		this.swiperInstances.forEach(function (swiper) {
			if (swiper && swiper.destroy) {
				swiper.destroy(true, true);
			}
		});
		this.swiperInstances = [];
	};

})(jQuery);
