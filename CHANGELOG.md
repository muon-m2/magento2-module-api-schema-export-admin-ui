# Changelog

All notable changes to this module are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
