<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div id="atx-vb-help-modal" style="display:none;">
	<div class="atx-vb-help__backdrop"></div>
	<div class="atx-vb-help__panel">
		<div class="atx-vb-help__header">
			<strong>How to use the Visual Nav Builder</strong>
			<button type="button" class="atx-vb-help__close">&times;</button>
		</div>
		<div class="atx-vb-help__body">

			<!-- Navigation Structure -->
			<h3>Navigation Structure</h3>
			<p>The navigation has a simple hierarchy:</p>
			<div class="atx-vb-help__diagram">
				<div class="atx-vb-help__level">
					<span class="atx-vb-help__tag atx-vb-help__tag--primary">Top-Level Item</span>
					<span class="atx-vb-help__arrow">&darr;</span>
				</div>
				<div class="atx-vb-help__level atx-vb-help__level--indent-1">
					<span class="atx-vb-help__tag atx-vb-help__tag--category">Category</span>
					<small>has icon</small>
					<span class="atx-vb-help__arrow">&darr;</span>
				</div>
				<div class="atx-vb-help__level atx-vb-help__level--indent-2">
					<span class="atx-vb-help__tag atx-vb-help__tag--sublink">Sub-link</span>
					<small>clickable page link</small>
				</div>
			</div>

			<!-- Quick Actions -->
			<h3>Quick Actions</h3>
			<table class="atx-vb-help__table">
				<tr>
					<td><strong>Menu location</strong></td>
					<td>Choose Header, Footer, or Primary V2 and preview it where the active theme renders it</td>
				</tr>
				<tr>
					<td><strong>Click an item</strong></td>
					<td>Select it and open the editor panel</td>
				</tr>
				<tr>
					<td><strong>Drag &#9783; handle</strong></td>
					<td>Reorder items within the same level</td>
				</tr>
				<tr>
					<td><strong>&#9660; / &#9654;</strong></td>
					<td>Collapse or expand children</td>
				</tr>
				<tr>
					<td><strong>&#128204; Pin</strong></td>
					<td>Keep a dropdown open; nested pins open their parents, and unpinning a parent clears its nested pins</td>
				</tr>
				<tr>
					<td><strong>Click in preview</strong></td>
					<td>Select that item in the tree</td>
				</tr>
				<tr>
					<td><strong>Ctrl + S</strong></td>
					<td>Save all changes</td>
				</tr>
			</table>

			<!-- Classes Explained -->
			<h3>Item Options (Classes)</h3>
			<p>Top-level items can have special behaviours. Select them from the <em>"+ Add class..."</em> dropdown:</p>
			<table class="atx-vb-help__table">
				<tr>
					<td><span class="atx-vb-help__class">CTA Button</span></td>
					<td>Makes the item a black pinned button on the right. <strong>Only one allowed.</strong></td>
				</tr>
				<tr>
					<td><span class="atx-vb-help__class">Secondary Navigation</span></td>
					<td>Opens a second navigation bar with tabs instead of a dropdown. Children become tabs. Use for complex categories like "Built-In Appliances".</td>
				</tr>
				<tr>
					<td><span class="atx-vb-help__class">Hide Slider & Brands</span></td>
					<td>Removes the promotional slider and brand logos from this item's dropdown.</td>
				</tr>
				<tr>
					<td><span class="atx-vb-help__class">2 / 3 / 4 Columns</span></td>
					<td>Controls how many columns the categories are split into. Default is 4.</td>
				</tr>
				<tr>
					<td><span class="atx-vb-help__class">Flyout Layout</span></td>
					<td>Sidebar-based dropdown: categories on the left, brands + slider on the right. Hovering a category reveals its sub-links in a panel. Each category can have its own brands.</td>
				</tr>
				<tr>
					<td><span class="atx-vb-help__class">Force Column Break</span></td>
					<td>Makes a category start in a new column. Available on category items only.</td>
				</tr>
				<tr>
					<td><span class="atx-vb-help__class">Placeholder</span></td>
					<td>Hides the item from the navigation. Used to make items appear to have a dropdown arrow.</td>
				</tr>
			</table>

			<!-- Flyout Layout -->
			<h3>Flyout Layout</h3>
			<p>The Flyout Layout gives a sidebar-style dropdown with cascading brands:</p>
			<ol>
				<li>Select a top-level item and add the <strong>"Flyout Layout"</strong> class</li>
				<li>Add categories as children &mdash; they appear in a left sidebar</li>
				<li>Add sub-links under categories &mdash; they appear in a panel on hover</li>
				<li>Set <strong>brands and slider</strong> on the top-level item &mdash; shown by default</li>
				<li>Set <strong>brands</strong> on individual categories &mdash; they replace the parent brands when that category is hovered</li>
			</ol>

			<!-- Slider & Brands -->
			<h3>Promotional Slider & Brand Logos</h3>
			<p>Top-level items with children can have a promotional slider and brand logos in their dropdown:</p>
			<ol>
				<li>Select a top-level item (or a tab in a secondary nav)</li>
				<li>Toggle <strong>"Promotional Slider"</strong> on</li>
				<li>Click <strong>"+ Add Slide"</strong> &mdash; fill in badge, title, description, prices</li>
				<li>Click the image area to pick a product photo</li>
				<li>Toggle <strong>"Brand Logos"</strong> on to add brand images</li>
			</ol>

			<!-- Secondary Navigation -->
			<h3>Secondary Navigation (e.g. Built-In Appliances)</h3>
			<p>For items with many sub-categories, use a secondary navigation:</p>
			<ol>
				<li>Select the top-level item</li>
				<li>Add the <strong>"Secondary Navigation"</strong> class</li>
				<li>Its children become <strong>tabs</strong> in a second navigation bar</li>
				<li>Add children to those tabs &mdash; they become <strong>categories with icons</strong></li>
				<li>Add children to categories &mdash; they become <strong>sub-links</strong></li>
			</ol>
			<div class="atx-vb-help__diagram">
				<div class="atx-vb-help__level">
					<span class="atx-vb-help__tag atx-vb-help__tag--primary">Built-In Appliances</span>
					<small>+ Secondary Navigation class</small>
				</div>
				<div class="atx-vb-help__level atx-vb-help__level--indent-1">
					<span class="atx-vb-help__tag atx-vb-help__tag--tab">Refrigeration</span>
					<small>tab (can have slider & brands)</small>
				</div>
				<div class="atx-vb-help__level atx-vb-help__level--indent-2">
					<span class="atx-vb-help__tag atx-vb-help__tag--category">Wine Coolers</span>
					<small>category with icon</small>
				</div>
				<div class="atx-vb-help__level atx-vb-help__level--indent-3">
					<span class="atx-vb-help__tag atx-vb-help__tag--sublink">4-Door Wine Coolers</span>
					<small>sub-link</small>
				</div>
			</div>

			<!-- Icons -->
			<h3>Icons</h3>
			<p>Category items can have icons. Select from the predefined icon library or upload a custom one. To add more predefined icons, drop SVG files into the <code>assets/icons/</code> folder.</p>

			<!-- Tips -->
			<h3>Tips</h3>
			<ul>
				<li>Use <code>|</code> in a title to force a line break (e.g. <code>Free Standing|Appliances</code>)</li>
				<li>Changes save to the database immediately for slider, brands, and icons</li>
				<li>Click <strong>"Save Changes"</strong> to save title, URL, and class changes</li>
				<li>The website preview updates automatically; use <strong>"Refresh"</strong> to force a reload</li>
				<li>Use <strong>Export / Import</strong> (in the menus page) to backup or sync between environments</li>
			</ul>

		</div>
	</div>
</div>
