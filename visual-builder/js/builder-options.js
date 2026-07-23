/**
 * Visual Builder - Class Manager
 * Dropdown picker with conflict/depth rules + removable tag pills.
 */
(function ($) {
	'use strict';

	let VB = window.AtxVB;

	/**
	 * Class registry with rules.
	 */
	let classRegistry = [
		{ cls: 'atx-nav-cta',     label: 'CTA Button',           group: null,   maxGlobal: 1,    conflicts: ['atx-no-extras', 'atx-cols-2', 'atx-cols-3', 'atx-cols-4', 'atx-flyout'], depths: [0] },
		{ cls: 'atx-nested-nav',  label: 'Secondary Navigation', group: null,   maxGlobal: null, conflicts: ['atx-no-extras', 'atx-flyout'], depths: [0] },
		{ cls: 'atx-flyout',      label: 'Flyout Layout',        group: null,   maxGlobal: null, conflicts: ['atx-nested-nav', 'atx-nav-cta', 'atx-cols-2', 'atx-cols-3', 'atx-cols-4', 'atx-no-extras'], depths: [0, 1], requiresChildren: true },
		{ cls: 'atx-no-extras',   label: 'Hide Slider & Brands', group: null,   maxGlobal: null, conflicts: ['atx-nav-cta', 'atx-nested-nav', 'atx-flyout'], depths: [0, 1], requiresChildren: true },
		{ cls: 'atx-cols-2',      label: '2 Columns',            group: 'cols', maxGlobal: null, conflicts: ['atx-cols-3', 'atx-cols-4', 'atx-nav-cta', 'atx-nested-nav', 'atx-flyout'], depths: [0, 1], requiresChildren: true },
		{ cls: 'atx-cols-3',      label: '3 Columns',            group: 'cols', maxGlobal: null, conflicts: ['atx-cols-2', 'atx-cols-4', 'atx-nav-cta', 'atx-nested-nav', 'atx-flyout'], depths: [0, 1], requiresChildren: true },
		{ cls: 'atx-cols-4',      label: '4 Columns',            group: 'cols', maxGlobal: null, conflicts: ['atx-cols-2', 'atx-cols-3', 'atx-nav-cta', 'atx-nested-nav', 'atx-flyout'], depths: [0, 1], requiresChildren: true },
		{ cls: 'atx-col-break',   label: 'Force Column Break',   group: null,   maxGlobal: null, conflicts: [], depths: null, categoryOnly: true },
		{ cls: 'atx-placeholder', label: 'Placeholder (hidden)', group: null,   maxGlobal: null, conflicts: [], depths: null, notDepth0: true },
	];

	let allManagedClasses = classRegistry.map(r => r.cls);

	// Extend openEditor
	let origOpenEditor = VB.openEditor;

	VB.openEditor = function (item) {
		origOpenEditor.call(this, item);
		if (!this.hasExtension || !this.hasExtension('mega-nav')) {
			$('#atx-vb-options-section, #atx-vb-classes-field').hide();
			return;
		}
		populateClassUI(item);
	};

	function getItemContext(item) {
		let depth = VB.getDepth(item.id);
		let isInsideNested = false;
		let current = item;
		while (current && current.parent_id) {
			let parent = VB.getItem(current.parent_id);
			if (parent && (parent.classes || []).includes('atx-nested-nav')) {
				isInsideNested = true;
				break;
			}
			current = parent;
		}
		let maxCatDepth = isInsideNested ? 2 : 1;
		return {
			depth,
			isInsideNested,
			isCategory: depth === maxCatDepth,
			isSubLink: depth > maxCatDepth,
		};
	}

	function populateClassUI(item) {
		let classes = item.classes || [];
		let ctx = getItemContext(item);

		// Show class picker on:
		// - Top-level items (depth 0)
		// - Nested tabs (depth 1 inside atx-nested-nav)
		// - Category items (limited to col-break only, with count validation)
		let showClasses = false;
		if (ctx.depth === 0) {
			showClasses = true;
		} else if (ctx.depth === 1 && ctx.isInsideNested) {
			showClasses = true;
		} else if (ctx.isCategory) {
			showClasses = true;
		}

		$('#atx-vb-options-section').toggle(showClasses);
		$('#atx-vb-classes-field').toggle(showClasses);

		if (!showClasses) return;

		renderClassTags(classes);
		populateClassPicker(item, classes, ctx);
		$('#atx-vb-edit-classes').val(classes.join(' '));
	}

	function renderClassTags(classes) {
		let $tags = $('#atx-vb-class-tags').html('');

		classes.forEach(cls => {
			if (!cls) return;
			let reg = classRegistry.find(r => r.cls === cls);
			let label = reg ? reg.label : cls;
			let isKnown = allManagedClasses.includes(cls);

			$tags.append(`
				<span class="atx-vb__tag${!isKnown ? ' atx-vb__tag--custom' : ''}" data-class="${cls}">
					${label}
					<button class="atx-vb__tag-remove" data-class="${cls}">&times;</button>
				</span>
			`);
		});
	}

	function getAncestorColCount(item) {
		let current = item;
		while (current) {
			let classes = current.classes || [];
			if (classes.includes('atx-cols-4')) return 4;
			if (classes.includes('atx-cols-3')) return 3;
			if (classes.includes('atx-cols-2')) return 2;
			current = current.parent_id ? VB.getItem(current.parent_id) : null;
		}
		return 1;
	}

	function getSiblingColBreakCount(item) {
		if (!item.parent_id) return 0;
		return VB.getChildren(item.parent_id)
			.filter(s => s.id !== item.id && (s.classes || []).includes('atx-col-break'))
			.length;
	}

	function populateClassPicker(item, currentClasses, ctx) {
		let $picker = $('#atx-vb-class-picker').html('<option value="">+ Add class...</option>');

		classRegistry.forEach(reg => {
			if (currentClasses.includes(reg.cls)) return;

			// Categories only see categoryOnly classes (e.g. col-break)
			if (ctx.isCategory && !reg.categoryOnly) return;

			if (reg.depths && !reg.depths.includes(ctx.depth)) return;
			if (reg.notDepth0 && ctx.depth === 0) return;
			if (reg.categoryOnly && !ctx.isCategory) return;
			if (reg.requiresChildren && VB.getChildren(item.id).length === 0) return;

			// col-break: only allowed if ancestor has multi-col and breaks < (columns - 1)
			if (reg.cls === 'atx-col-break') {
				let colCount = getAncestorColCount(item);
				let breakCount = getSiblingColBreakCount(item);
				if (colCount < 2 || breakCount >= colCount - 1) return;
			}

			if (reg.maxGlobal) {
				let count = VB.items.filter(i => (i.classes || []).includes(reg.cls)).length;
				if (count >= reg.maxGlobal) return;
			}

			if (reg.conflicts.some(c => currentClasses.includes(c))) return;

			if (reg.group) {
				let groupMembers = classRegistry.filter(r => r.group === reg.group).map(r => r.cls);
				if (groupMembers.some(c => currentClasses.includes(c))) return;
			}

			$picker.append(`<option value="${reg.cls}">${reg.label}</option>`);
		});
	}

	// Add class from picker
	$(document).on('change', '#atx-vb-class-picker', function () {
		let cls = $(this).val();
		if (!cls) return;

		let id = parseInt($('#atx-vb-edit-id').val(), 10);
		let item = VB.getItem(id);
		if (!item) return;

		if (!item.classes) item.classes = [];
		if (!item.classes.includes(cls)) item.classes.push(cls);

		$(this).val('');
		populateClassUI(item);
		VB.markDirty();
		VB.renderTree();
	});

	// Remove class tag
	$(document).on('click', '.atx-vb__tag-remove', function () {
		let cls = $(this).data('class');
		let id = parseInt($('#atx-vb-edit-id').val(), 10);
		let item = VB.getItem(id);
		if (!item) return;

		item.classes = (item.classes || []).filter(c => c !== cls);
		populateClassUI(item);
		VB.markDirty();
		VB.renderTree();
	});

})(jQuery);
