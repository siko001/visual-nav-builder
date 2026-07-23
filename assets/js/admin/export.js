/**
 * Atx Nav Menu Admin - Export / Import
 */
(function ($) {
	'use strict';

	let $log = $('#atx-export-log');

	function showLog(message) {
		$log.show().append(`<div>${message}</div>`);
		$log[0].scrollTop = $log[0].scrollHeight;
	}

	function clearLog() {
		$log.html('').hide();
	}

	function menuLocation() {
		return $('#atx-export-menu-location').val() || 'primary-v2';
	}

	$('#atx-export-menu-location').on('change', function () {
		let url = new URL(window.location.href);
		url.searchParams.set('menu_location', menuLocation());
		window.location.href = url.toString();
	});

	// Export
	$('#atx-export-btn').on('click', function () {
		let $btn = $(this);
		let $status = $('#atx-export-status');

		$btn.prop('disabled', true).text('Exporting...');
		$status.text('');
		clearLog();

		$.ajax({
			url: ajaxurl,
			method: 'POST',
			data: {
				action: 'atx_nav_export',
				_wpnonce: atxNavAdmin.nonce,
				menu_location: menuLocation()
			},
			success: function (response) {
				$btn.prop('disabled', false).text('Export Menu');
				if (response.success) {
					$status.html(`<span style="color:green;">Exported successfully!</span> <a href="${response.data.url}" download>Download</a>`);
					if (response.data.log) {
						response.data.log.forEach(showLog);
					}
				} else {
					$status.html(`<span style="color:red;">Export failed: ${response.data}</span>`);
				}
			},
			error: function () {
				$btn.prop('disabled', false).text('Export Menu');
				$status.html('<span style="color:red;">Export failed. Check console.</span>');
			}
		});
	});

	// Import from file upload
	$('#atx-import-btn').on('click', function () {
		let file = $('#atx-import-file')[0].files[0];
		if (!file) {
			$('#atx-import-status').html('<span style="color:red;">Please select a JSON file.</span>');
			return;
		}

		let $btn = $(this);
		let $status = $('#atx-import-status');

		$btn.prop('disabled', true).text('Importing...');
		$status.text('');
		clearLog();

		let reader = new FileReader();
		reader.onload = function (e) {
			$.ajax({
				url: ajaxurl,
				method: 'POST',
				data: {
					action: 'atx_nav_import',
					_wpnonce: atxNavAdmin.nonce,
					menu_location: menuLocation(),
					json_data: e.target.result
				},
				success: function (response) {
					$btn.prop('disabled', false).text('Import Menu');
					if (response.success) {
						$status.html('<span style="color:green;">Imported successfully! Reload the page to see changes.</span>');
						if (response.data.log) {
							response.data.log.forEach(showLog);
						}
					} else {
						$status.html(`<span style="color:red;">Import failed: ${response.data}</span>`);
					}
				},
				error: function () {
					$btn.prop('disabled', false).text('Import Menu');
					$status.html('<span style="color:red;">Import failed. Check console.</span>');
				}
			});
		};
		reader.readAsText(file);
	});

	// Import from server file
	$(document).on('click', '.atx-import-from-server', function () {
		if (!confirm('This will replace the current menu. Continue?')) return;

		let $btn = $(this);
		let filepath = $btn.data('file');

		$btn.prop('disabled', true).text('Importing...');
		clearLog();

		$.ajax({
			url: ajaxurl,
			method: 'POST',
			data: {
				action: 'atx_nav_import_file',
				_wpnonce: atxNavAdmin.nonce,
				menu_location: menuLocation(),
				filepath: filepath
			},
			success: function (response) {
				$btn.prop('disabled', false).text('Import This');
				if (response.success) {
					showLog('Import complete! Reload page to see changes.');
					if (response.data.log) {
						response.data.log.forEach(showLog);
					}
				} else {
					showLog('Import failed: ' + response.data);
				}
			}
		});
	});

	$('#atx-import-default').on('click', function () {
		if (!confirm('This will replace the selected menu location with its default export. Continue?')) return;

		let $btn = $(this);
		let $status = $('#atx-default-status');
		$btn.prop('disabled', true).text('Importing...');
		$status.text('');
		clearLog();

		$.ajax({
			url: ajaxurl,
			method: 'POST',
			data: {
				action: 'atx_nav_import_default',
				_wpnonce: atxNavAdmin.nonce,
				menu_location: menuLocation()
			},
			success: function (response) {
				$btn.prop('disabled', false).text('Import Default for Selected Location');
				if (response.success) {
					$status.html('<span style="color:green;">Default imported. Reload to see changes.</span>');
					if (response.data.log) response.data.log.forEach(showLog);
				} else {
					$status.html(`<span style="color:red;">Import failed: ${response.data}</span>`);
				}
			}
		});
	});

	$(document).on('click', '.atx-set-default-export', function () {
		let file = $(this).data('file');
		let $status = $('#atx-default-status');

		$.ajax({
			url: ajaxurl,
			method: 'POST',
			data: {
				action: 'atx_nav_set_default_export',
				_wpnonce: atxNavAdmin.nonce,
				menu_location: menuLocation(),
				file: file
			},
			success: function (response) {
				if (response.success) {
					$('#atx-current-default').text(file);
					$('#atx-import-default').prop('disabled', false);
					$status.html('<span style="color:green;">Default export updated.</span>');
				} else {
					$status.html(`<span style="color:red;">Could not set default: ${response.data}</span>`);
				}
			}
		});
	});

	$(document).on('click', '.atx-delete-export', function () {
		let file = $(this).data('file');
		if (!confirm('Delete this export file?')) return;

		let $row = $(this).closest('tr');
		$.ajax({
			url: ajaxurl,
			method: 'POST',
			data: {
				action: 'atx_nav_delete_export',
				_wpnonce: atxNavAdmin.nonce,
				file: file
			},
			success: function (response) {
				if (response.success) {
					$row.remove();
					showLog('Deleted export: ' + file);
				} else {
					showLog('Delete failed: ' + response.data);
				}
			}
		});
	});

	// Reset to baseline
	$('#atx-reset-baseline').on('click', function () {
		if (!confirm('Are you sure? This will replace the entire navigation with the default configuration. This cannot be undone.')) return;

		let $btn = $(this);
		let $status = $('#atx-reset-status');

		$btn.prop('disabled', true).text('Resetting...');
		$status.text('');
		clearLog();

		$.ajax({
			url: ajaxurl,
			method: 'POST',
			data: {
				action: 'atx_nav_reset_baseline',
				_wpnonce: atxNavAdmin.nonce,
				menu_location: menuLocation()
			},
			success: function (response) {
				$btn.prop('disabled', false).text('Reset to Default');
				if (response.success) {
					$status.html('<span style="color:green;">Reset complete! Reload to see changes.</span>');
					if (response.data.log) {
						response.data.log.forEach(showLog);
					}
				} else {
					$status.html(`<span style="color:red;">Reset failed: ${response.data}</span>`);
				}
			},
			error: function () {
				$btn.prop('disabled', false).text('Reset to Default');
				$status.html('<span style="color:red;">Reset failed.</span>');
			}
		});
	});

})(jQuery);
