# Changelog

All notable changes to this module are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [1.0.0] - 2026-10-09

First stable release.

### Added

- `<link rel="alternate" hreflang="…">` tags in the page head for the home page, products, categories and CMS pages, with self-reference, reciprocity, a minimum of two versions and an optional `x-default`.
- Products and categories paired by entity ID; URLs from each store view's URL rewrites, matching the canonical URL.
- CMS page translation groups with an admin grid and form (CMS → Hreflang Translation Groups); pages assigned to all or several store views pair automatically.
- Configuration: hreflang code per store view validated against ISO 639-1 / ISO 3166-1 and unique within the alternates scope, alternates scope (website or all store views), `x-default` store view, excluded actions, store views served without the store code in the URL.
- Exclusions: 404 page, search, checkout, customer account and other configured actions, `noindex` pages and store views, pages with query parameters other than tracking ones.
- Block cache per store view, page and protocol, refreshed on entity, translation group and configuration changes.
- `bpf_hreflang_resolvers_collect` event for resolvers of other content types.
- English and Polish translations.

## [0.9.0] - 2026-10-09

### Added

- Admin for CMS page translation groups.

## [0.8.1] - 2026-10-09

### Fixed

- The CMS 404 page opened at its own URL no longer gets tags.
- A CMS page shown in a store view where its translation group has another page no longer gets tags.

## [0.8.0] - 2026-10-09

### Added

- Translation group tables (module version 1.0.0 setup) and CMS page resolver.

## [0.7.0] - 2026-10-09

### Added

- Product and category resolvers.

## [0.6.0] - 2026-10-09

### Added

- Hreflang tags on the home page, with exclusions and block cache.

## [0.5.0] - 2026-10-09

### Added

- URL service for home, product, category and CMS page URLs in other store views.

## [0.4.0] - 2026-10-09

### Changed

- PHP 8.2 or newer is required.

### Added

- Locale codes are validated against ISO 639-1 and ISO 3166-1.

## [0.3.0] - 2026-10-09

### Added

- Configuration section with locale code validation.

[1.0.0]: https://github.com/bpf-net/openmage_hreflang/compare/v0.9.0...v1.0.0
[0.9.0]: https://github.com/bpf-net/openmage_hreflang/compare/v0.8.1...v0.9.0
[0.8.1]: https://github.com/bpf-net/openmage_hreflang/compare/v0.8.0...v0.8.1
[0.8.0]: https://github.com/bpf-net/openmage_hreflang/compare/v0.7.0...v0.8.0
[0.7.0]: https://github.com/bpf-net/openmage_hreflang/compare/v0.6.0...v0.7.0
[0.6.0]: https://github.com/bpf-net/openmage_hreflang/compare/v0.5.0...v0.6.0
[0.5.0]: https://github.com/bpf-net/openmage_hreflang/compare/v0.4.0...v0.5.0
[0.4.0]: https://github.com/bpf-net/openmage_hreflang/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/bpf-net/openmage_hreflang/compare/v0.2.0...v0.3.0
