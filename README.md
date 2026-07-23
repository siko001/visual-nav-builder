# Visual Nav Builder

Standalone WordPress plugin for visually editing any registered navigation menu
against a live preview of the real website.

## Features

- Dynamically discovers registered Header, Footer, and custom menu locations.
- Renders each menu where the active theme places it.
- Supports live unsaved edits, adding, deleting, and drag-and-drop ordering.
- Remembers the selected menu location and preview device after refresh.
- Uses fixed Desktop, Tablet, and Mobile viewport widths with readable scaling.
- Opens all required ancestors when a nested dropdown is pinned.
- Clears descendant pins when a pinned parent is closed.
- Forces menu text visible while pinned, regardless of GSAP/theme animations.
- Suppresses Complianz, consent banners, marketing modals, and popups only
  inside the builder preview.
- Keeps the normal public website and its consent behavior unchanged.
- Includes optional Primary V2 support, disabled in code by default.

## Installation

1. Download `visual-nav-builder.zip` from the latest GitHub Release.
2. In WordPress, open **Plugins > Add New > Upload Plugin**.
3. Upload and activate the ZIP.
4. Open **Appearance > Visual Nav Builder**.

The plugin stores content in WordPress's native menu tables. Deactivating or
removing it does not delete saved menus or menu items.

## Primary V2

Primary V2 is disabled by default in `atx-nav-menu-config.php`:

```php
'v2_enabled' => false,
```

Set it to `true`, or define `ATX_NAV_V2_ENABLED` as `true` before the plugin
loads, to restore it without losing its saved WordPress menu data.

## Updates

The updater is isolated under the `AtxVisualNavBuilder` PHP namespace and checks
GitHub Releases from:

`siko001/visual-nav-builder`

Create semantic release tags such as `v1.1.6`. The included GitHub Actions
workflow builds and uploads `visual-nav-builder.zip`, which installed copies use
for WordPress updates.
