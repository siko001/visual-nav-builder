=== ATX Nav Visual Builder ===
Contributors: atx
Tags: navigation, menus, visual builder, live preview
Requires at least: 6.0
Requires PHP: 8.1
Stable tag: 1.1.5
License: GPLv2 or later

Edit any registered WordPress menu location in a live preview of the real website.

== Description ==

ATX Nav Visual Builder provides the same self-contained visual menu builder used
by the ATX theme module, packaged for installation on existing WordPress sites.

Features:

* Select any registered Header, Footer, or custom menu location.
* Preview the menu where the active theme actually renders it.
* Edit, add, delete, and reorder WordPress menu items.
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

= 1.1.5 =

* Initial standalone plugin release matching the ATX theme module.
* Added the namespaced GitHub Releases updater.
