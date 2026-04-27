# Changelog

## [Unreleased](https://github.com/duoncode/router/compare/0.1.0...HEAD)

### Changed

- `Router::match()` now returns `RouteMatch` instead of mutating and returning the matched `Route`.
- `Dispatcher::dispatch()` and `View` now consume `RouteMatch` so route params are request-local.
- `MethodNotAllowedException` now exposes `allowedMethods()` and no longer extends `NotFoundException`.
- Named route URLs are generated with `Router::url()` and now include the router global prefix, optional host, query params, and strict path param validation.
- Static asset URLs are generated with `Router::asset()`.
- Aligned view argument resolution with the latest `duon/wire` behavior.
- `View` now bubbles exceptions thrown while constructing autowired dependencies instead of always wrapping them in `Duon\Router\Exception\RuntimeException`.
- Default parameter values are still used when argument resolution itself fails.

## [0.1.0](https://github.com/duoncode/router/releases/tag/0.1.0) (2026-01-31)

Initial release.

### Added

- PSR-7/PSR-15 compatible router and dispatcher
- Route definition with parameters and constraints
- Middleware support and route grouping
