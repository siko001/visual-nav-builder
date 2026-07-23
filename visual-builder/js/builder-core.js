/**
 * Visual Builder - Core
 * State management, initialization, data loading.
 */
(function ($) {
	'use strict';

	window.AtxVB = {
		items: [],
		selectedId: null,
		menuId: null,
		menuLocation: '',
		extensions: [],
		dirty: false,

		init: function () {
			let initialLocation = atxVB.menuLocation || $('#atx-vb-menu-location').val() || '';

			try {
				const urlLocation = new URL(window.location.href).searchParams.get('menu_location');
				const savedLocation = window.localStorage.getItem('atx_vb_menu_location');
				const isKnownLocation = function (location) {
					return Boolean(
						location &&
						atxVB.locations &&
						Object.prototype.hasOwnProperty.call(atxVB.locations, location)
					);
				};

				if (isKnownLocation(urlLocation)) {
					initialLocation = urlLocation;
				} else if (isKnownLocation(savedLocation)) {
					initialLocation = savedLocation;
				}
			} catch (e) {
				// Storage or URL APIs may be unavailable in a restricted browser.
			}

			this.menuLocation = initialLocation;
			$('#atx-vb-menu-location').val(this.menuLocation);
			this.persistMenuLocation();
			this.extensions = atxVB.extensions || [];
			this.load();
			this.bindGlobal();
		},

		persistMenuLocation: function () {
			if (!this.menuLocation) return;

			try {
				window.localStorage.setItem('atx_vb_menu_location', this.menuLocation);
				const url = new URL(window.location.href);
				url.searchParams.set('menu_location', this.menuLocation);
				window.history.replaceState({}, '', url.toString());
			} catch (e) {
				// The current selection still works for this browser session.
			}
		},

		hasExtension: function (name) {
			return (this.extensions || []).includes(name);
		},

		load: function () {
			let self = this;
			$.ajax({
				url: atxVB.ajaxUrl,
				method: 'POST',
				data: { action: 'atx_vb_load', _wpnonce: atxVB.nonce, menu_location: this.menuLocation },
				success: function (res) {
					if (res.success) {
						self.items = res.data.items;
						self.menuId = res.data.menu_id;
						self.menuLocation = res.data.menu_location;
						self.extensions = res.data.extensions || [];
						$('#atx-vb-menu-location').val(self.menuLocation);
						self.persistMenuLocation();
						$('#atx-vb').toggleClass('atx-vb--mega', self.hasExtension('mega-nav'));
						self.renderTree();
						self.refreshPreview();
					} else {
						$('#atx-vb-status').text(res.data || 'Could not load menu').css('color', '#e44');
					}
				}
			});
		},

		getItem: function (id) {
			return this.items.find(i => i.id === id);
		},

		getChildren: function (parentId) {
			return this.items.filter(i => i.parent_id === parentId);
		},

		getDepth: function (id) {
			let depth = 0;
			let item = this.getItem(id);
			while (item && item.parent_id) {
				depth++;
				item = this.getItem(item.parent_id);
			}
			return depth;
		},

		/**
		 * Recalculate all positions based on tree order (depth-first).
		 * Call this after any structural change (add, delete, reorder).
		 */
		recalcPositions: function () {
			let pos = 1;
			let self = this;

			function walk(parentId) {
				let children = self.items.filter(i => i.parent_id === parentId);
				children.sort((a, b) => a.position - b.position);
				children.forEach(child => {
					child.position = pos++;
					walk(child.id);
				});
			}

			walk(0);
		},

		markDirty: function () {
			this.dirty = true;
			$('#atx-vb-status').text('Unsaved changes').css('color', '#f0ad4e');
		},

		notify: function (message, type) {
			let $stack = $('#atx-vb-toast-stack');
			if (!$stack.length) return;

			let $toast = $('<div class="atx-vb-toast"></div>')
				.addClass(type ? 'atx-vb-toast--' + type : '')
				.text(message || '');

			$stack.append($toast);
			setTimeout(function () {
				$toast.addClass('atx-vb-toast--dismissing');
				setTimeout(function () { $toast.remove(); }, 260);
			}, 5000);
		},

		bindGlobal: function () {
			let self = this;

			// Warn on leaving with unsaved changes
			$(window).on('beforeunload', function () {
				if (self.dirty) return 'You have unsaved changes.';
			});

			// Search — show matching items + their parents
			$('#atx-vb-search').on('input', function () {
				let query = $(this).val().toLowerCase();

				if (!query) {
					$('.atx-vb-item').show();
					return;
				}

				// First hide all
				$('.atx-vb-item').hide();

				// Find matches and show them + all ancestors
				$('.atx-vb-item').each(function () {
					let title = $(this).find('.atx-vb-item__title').text().toLowerCase();
					if (title.includes(query)) {
						$(this).show();
						// Show all parent items
						let parentId = parseInt($(this).data('parent'), 10);
						while (parentId) {
							let $parent = $(`.atx-vb-item[data-id="${parentId}"]`);
							$parent.show();
							parentId = parseInt($parent.data('parent'), 10) || 0;
						}
					}
				});
			});

			$('#atx-vb-menu-location').on('change', function () {
				if (self.dirty && !confirm('Switch menus and discard unsaved changes?')) {
					$(this).val(self.menuLocation);
					return;
				}

				self.menuLocation = $(this).val();
				self.persistMenuLocation();
				self.selectedId = null;
				self.dirty = false;
				$('#atx-vb-editor').hide();
				$('#atx-vb-status').text('Loading...').css('color', '#666');
				self.load();
			});

		},

		// Placeholder methods — filled by other modules
		renderTree: function () {},
		openEditor: function () {},
		refreshPreview: function () {},
	};

	$(document).ready(function () {
		window.AtxVB.init();
	});

})(jQuery);
