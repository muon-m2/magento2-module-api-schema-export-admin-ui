# Changelog

All notable changes to this module are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.2] - 2026-09-28

### Fixed

- **The module's group in the MUON admin-menu hub showed the generic fallback glyph.**
  `Muon_AdminMenu` paints every group a `package` cube by default and expects each module to
  override it; this module never shipped a rule, so its hub entry was visually identical to the
  other modules that had also not supplied one. It now ships
  `view/adminhtml/web/css/admin-menu.css` (Tabler `api`), registered from
  `view/adminhtml/layout/default.xml`.

  The selector carries a type qualifier (`#nav li[data-ui-id=...]`) because Magento emits `<css>`
  links in alphabetical module order and `Muon_AdminMenu` sorts first — equal specificity would lose
  to the fallback. Only `mask-image` is declared; geometry comes from the hub's shared rule.

### Added

- `docs/third-party-notices.md` — reproduces the Tabler Icons MIT licence in full, which a bare URL
  does not satisfy.

## [1.0.1] - 2026-08-31

### Fixed

- Relaxed the `ext-zip` requirement from `>=1.15.0` to `*`. An extension's version is fixed by the
  PHP build and cannot be satisfied by the resolver, so the floor could only ever reject a host —
  and the module uses no ZipArchive API newer than PHP 5.2 (`open()`, `addFromString()`,
  `close()`). Builds whose reported `ext-zip` version tracks libzip rather than the PECL release
  can now install the module.

## [1.0.0] - 2026-08-24

### Added

- Admin screen under **System → API Schema Export** for generating and downloading API
  description files.
- Single-file download, and a ZIP when a request produces more than one file.
- ACL filtering: the resolved surface is restricted to endpoints the signed-in administrator is
  permitted to call.
