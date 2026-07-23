=== ATX Nav Visual Builder ===
Contributors: atx
Tags: navigation, menus, visual builder, live preview
Requires at least: 6.0
Requires PHP: 8.1
Stable tag: 1.2.0
License: GPLv2 or later

Edit any registered WordPress menu location in a live preview of the real website.

== Description ==

ATX Nav Visual Builder provides the same self-contained visual menu builder used
by the ATX theme module, packaged for installation on existing WordPress sites.

Features:

* Select any registered Header, Footer, or custom menu location.
* Preview the menu where the active theme actually renders it.
* Edit, add, delete, and reorder WordPress menu items.
* Stage the entire menu and save it atomically in one operation.
* Undo and redo structural, content, option, and media changes.
* Recover unsaved drafts after a refresh, closed tab, or browser crash.
* Check menu health for weak labels, placeholders, duplicates, deep nesting,
  missing content, unpublished content, and unreachable external links.
* Restore any of the latest 10 saved revisions.
* Detect another administrator's newer edits before overwriting them.
* Duplicate complete branches or bulk move, duplicate, copy, and delete items.
* Preview unsaved changes automatically.
* Remember the selected location and preview device across refreshes.
* Fixed Desktop, Tablet, and Mobile preview widths with readable scaling.
* Pin dropdowns open; nested pins automatically open their ancestors.
* Unpinning a parent clears all pinned descendants.
* Force animated menu text visible while dropdowns are pinned.
* Suppress consent banners, marketing modals, and popups inside previews only.
* Preserve the normal public website and its consent behavior.
* Optional Primary V2 mega-navigation support, disabled from code by default.

The plugin stores menu content using WordPress's native navigation menu data.
Deactivating or removing the plugin does not delete saved menus or menu items.

== Installation ==

1. Upload `visual-nav-builder.zip` through Plugins > Add New > Upload Plugin.
2. Activate ATX Nav Visual Builder.
3. Open Appearance > Visual Nav Builder.
4. Select a registered menu location and begin editing.

== Primary V2 code switch ==

Primary V2 is disabled by default in `atx-nav-menu-config.php`:

`'v2_enabled' => false`

Set it to `true`, or define `ATX_NAV_V2_ENABLED` as `true` before the plugin
loads, to register and render Primary V2. Its saved menu data is preserved while
disabled.

== Notes ==

The live preview uses the active theme's real frontend page. A theme must render
the selected WordPress menu location on that page for an in-place preview.
Primary V2 also includes a standalone fallback preview.

The optional Primary V2 frontend renderer retains compatibility with the
`visualcomposerstarter_after_header_menu` hook used by existing ATX sites.

Updates are read from GitHub Releases in `siko001/visual-nav-builder`. Each
release must include an asset named `visual-nav-builder.zip`.

== Changelog ==

= 1.2.0 =

* Added transaction-backed staged saving so adds, deletes, item fields, ACF values, icons, sliders, and brands are committed together.
* Added Undo and Redo for menu structure, labels, destinations, classes, options, media, and menu naming.
* Added automatic browser crash and refresh recovery for unsaved menu drafts.
* Added Menu Health diagnostics for labels, placeholder links, duplicate destinations, deep nesting, deleted/unpublished content, missing terms, and external link failures.
* Added a 10-entry revision history with one-click restoration and a safety revision of the replaced menu.
* Added optimistic edit-conflict protection with Reload Latest and explicit Overwrite choices.
* Added complete-branch duplication plus bulk selection, movement, duplication, deletion, and copying to another registered menu location.
* Kept all new features synchronized between the standalone plugin and the ATX theme module.

= 1.1.9 =

* Show only friendly content-type labels in Add Existing results.
* Prevent slower previous searches from overwriting newer CPT results.

= 1.1.8 =

* Added a close control and Escape-key support to the Add Existing panel.
* Added compact Custom Root and Existing Root actions for empty menus.
* Kept item-editor add actions on one line in narrow panels.
* Search custom post types and taxonomies by their label, singular label, or slug.
* Versioned every builder stylesheet directly so updates no longer require a hard refresh.

= 1.1.7 =

* Made preview location focusing resilient to Lenis, sticky sections, and late layout changes.
* Footer locations now always focus the true bottom of the preview page.
* Automatically open selected mobile and off-canvas menus in previews without depending on theme JavaScript or animations.
* Added menu renaming directly in the visual builder without changing the theme location.
* Hide Collapse All and Expand All automatically for flat menus in both the visual builder and WordPress Menus screen.
* Decode WordPress title entities so labels such as `Terms & Conditions` display normally.
* Improved Add Existing results for narrow panels and dynamically retarget child actions when another menu item is selected.
* Added Add Custom Page and Add Existing Page actions directly to the item editor.
* Replaced legacy `crosscraft-child` references with the `atx_theme` text domain.
* Preserve nonstandard installed folder names during GitHub Release updates.

= 1.1.5 =

* Initial standalone plugin release matching the ATX theme module.
* Added the namespaced GitHub Releases updater.
