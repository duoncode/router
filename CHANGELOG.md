# Changelog

## [Unreleased](https://github.com/duonrun/router/compare/0.1.0...HEAD)

### Changed

- Aligned view argument resolution with the latest `duon/wire` behavior.
- `View` now bubbles exceptions thrown while constructing autowired dependencies instead of always wrapping them in `Duon\Router\Exception\RuntimeException`.
- Default parameter values are still used when argument resolution itself fails.

## [0.1.0](https://github.com/duonrun/router/releases/tag/0.1.0) (2026-01-31)

Initial release.

### Added

- PSR-7/PSR-15 compatible router and dispatcher
- Route definition with parameters and constraints
- Middleware support and route grouping
