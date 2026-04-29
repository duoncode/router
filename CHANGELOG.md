# Changelog

## [Unreleased](https://github.com/duoncode/router/compare/0.1.0...HEAD)

### Added

- `RoutingHandler` provides a PSR-15 request handler that matches and dispatches a request in one call.
- `Router::group()` registers callback groups without manually constructing `Group` objects.
- Route adders now expose `any()` for methodless routes.
- Route adders now expose `map()` for routes with an explicit method list.

### Breaking

- `Router::match()` now returns `RouteMatch` instead of mutating and returning the matched `Route`.
- `Route::match()` now returns matched route params and replaces `Route::matchPath()`.
- `Dispatcher::dispatch()` and `View` now consume `RouteMatch` so route params are request-local.
- `MethodNotAllowedException` no longer extends `NotFoundException`.
- Named route URLs are generated with `Router::url()` and now include the router global prefix, optional host, query params, and strict path param validation.
- Static asset URLs are generated with `Router::asset()`.
- `Router::routes()` was removed; register routes directly on `Router` or use `Router::group()` for scoped callbacks.
- The methodless route helper is now `any()`; the old `route()` helper was removed.
- `Endpoint` and the `endpoint()` route-adder helper were removed.
- `Router::addGroup()` and `Group::addGroup()` were removed; use `Router::group()` or nested `$group->group()` callbacks instead.
- `Router::group()` and nested `$group->group()` now return `void`; define all group configuration inside the callback.
- `Group` instances can only be modified while their own callback is running.
- Manual `Group` construction was removed. Group lifecycle methods are internal.

### Changed

- `MethodNotAllowedException` now exposes `allowedMethods()`.
- Aligned view argument resolution with the latest `duon/wire` behavior.
- `View` now bubbles exceptions thrown while constructing autowired dependencies instead of always wrapping them in `Duon\Router\Exception\RuntimeException`.
- Default parameter values are still used when argument resolution itself fails.
- Group middleware, `Before` handlers, `After` handlers, and controller settings are collected while the group callback runs and then applied to all group routes.
- Invalid route action errors now distinguish missing controllers, missing methods, unsupported bare method strings, and non-callable controller methods.

## [0.1.0](https://github.com/duoncode/router/releases/tag/0.1.0) (2026-01-31)

Initial release.

### Added

- PSR-7/PSR-15 compatible router and dispatcher
- Route definition with parameters and constraints
- Middleware support and route grouping
