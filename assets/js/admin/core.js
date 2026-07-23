/**
 * Atx Nav Menu Admin - Core
 * Toggles, template helper.
 */
(function ($) {
	'use strict';

	window.atxNavAdmin = window.atxNavAdmin || { nonce: '' };

	/**
	 * Render an HTML template from a <script type="text/html"> block.
	 * Replaces {{key}} placeholders with values from the data object.
	 *
	 * Usage: atxNavAdmin.template('atx-tmpl-slide-row', { itemId: 123, index: 0 })
	 *
	 * @param {string} id   - The script element ID (without #)
	 * @param {object} data - Key/value pairs to replace {{key}} placeholders
	 * @returns {string} Rendered HTML
	 */
	window.atxNavAdmin.template = function (id, data = {}) {
		let html = document.getElementById(id)?.innerHTML || '';
		Object.keys(data).forEach(key => {
			html = html.replace(new RegExp(`\\{\\{${key}\\}\\}`, 'g'), data[key]);
		});
		return html.trim();
	};

	// Toggles
	$(document).on('change', '.atx-nav-slider-toggle', function () {
		$(this).closest('.atx-nav-extras')
			.find('.atx-nav-slider-config').toggle(this.checked);
	});

	$(document).on('change', '.atx-nav-brands-toggle', function () {
		$(this).closest('.atx-nav-extras')
			.find('.atx-nav-brands-config').toggle(this.checked);
	});

})(jQuery);
