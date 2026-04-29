---
title: Introduction
---

# Duon Router

Duon Router is a PSR-7/PSR-15 router and view dispatcher. Define routes on `Router`, run requests through `RoutingHandler`, and use named routes for strict URL generation.

## Installation

Install the package with Composer:

```bash
composer require duon/router
```

Duon Router targets PHP 8.5 and works with PSR-7 requests/responses, PSR-11 containers, PSR-15 middleware, and PSR-15 request handlers. You provide your preferred PSR-7/PSR-17 implementation.

## Quickstart

Using a PSR-7 request and PSR-17 response factory:

```php
use Duon\Router\Dispatcher;
use Duon\Router\Router;
use Duon\Router\RoutingHandler;

$router = new Router();
$router->get('/health', function () use ($responseFactory) {
    return $responseFactory->createResponse(204);
}, 'health');

$handler = new RoutingHandler($router, new Dispatcher());
$response = $handler->handle($request);
```

`RoutingHandler` implements `Psr\Http\Server\RequestHandlerInterface`. It matches the request and dispatches the matched route in one call.

## Routes

Define routes with HTTP method helpers. Use `any()` when a route intentionally accepts every request method.

```php
$router->get('/health', [HealthController::class, 'show'], 'health');
$router->post('/albums', [AlbumController::class, 'create'], 'albums.create');
$router->any('/webhook', $webhook, 'webhook');
```

Available helpers are:

- `any()`
- `get()`
- `post()`
- `put()`
- `patch()`
- `delete()`
- `head()`
- `options()`

Use `addRoute()` when you need to construct a `Route` directly:

```php
use Duon\Router\Route;

$route = Route::get('/albums', [AlbumController::class, 'index'], 'albums.index');
$router->addRoute($route);
```

### Route patterns

Route patterns are matched against the request path:

```php
$router->get('/albums/{id}', [AlbumController::class, 'show'], 'albums.show');
$router->get('/albums/{id:\d+}', [AlbumController::class, 'show'], 'albums.show');
$router->get('/media/...slug', [MediaController::class, 'show'], 'media.show');
```

Supported tokens:

- `{name}` matches one path segment.
- `{id:\d+}` matches one path segment and validates it with the regex constraint.
- `...slug` captures the rest of the path, including slashes.

Parameter names must be unique within a route pattern.

### Actions

Preferred route actions are callables and controller method arrays:

```php
$router->get('/status', fn() => $response);
$router->get('/albums', [AlbumController::class, 'index']);
```

Invokable class strings and legacy `Class::method` strings are also supported. Inside a controller group, route actions can be bare method names. Outside a controller group, use a callable or `[Controller::class, 'method']`.

### Names

Pass a route name as the third argument when you want URL generation:

```php
$router->get('/albums/{id:\d+}', [AlbumController::class, 'show'], 'albums.show');

$url = $router->url('albums.show', ['id' => 13]);
// /albums/13
```

Route names must be unique.

## Groups

Groups compose route prefixes, name prefixes, middleware, `Before` handlers, `After` handlers, and controller classes. Configure groups only inside the group callback. `Router::group()` and nested `$group->group()` return `void`; the group is finalized after the callback completes.

Group settings are collected while the callback runs and then applied to all routes in that group, even when the settings are declared after route lines.

```php
use Duon\Router\Group;

$router->group('/albums', function (Group $albums) use ($auth): void {
    $albums->get('', 'index', 'albums.index');
    $albums->get('/{id:\d+}', 'show', 'albums.show');
    $albums->post('', 'create', 'albums.create');

    $albums->middleware($auth);
    $albums->controller(AlbumController::class);
});
```

The example above registers these names and paths:

- `albums.index` at `/albums`
- `albums.show` at `/albums/{id:\d+}`
- `albums.create` at `/albums`

Nested groups append path and name prefixes:

```php
$router->group('/api', function (Group $api): void {
    $api->group('/v1', function (Group $v1): void {
        $v1->get('/health', [HealthController::class, 'show'], 'health');
    }, 'v1.');
}, 'api.');

$router->url('api.v1.health');
// /api/v1/health
```

A group instance can only be modified while its own callback is running. This keeps route registration order and finalization predictable.

## Matching and dispatching

Use `RoutingHandler` for normal request handling:

```php
$handler = new RoutingHandler($router, new Dispatcher(), $container);
$response = $handler->handle($request);
```

The container is optional. When provided, it is used while constructing controllers, autowiring view arguments, and resolving `#[Call]` hooks on attributes.

`RoutingHandler` lets `NotFoundException` and `MethodNotAllowedException` bubble by default so your application can render 404 and 405 responses. `MethodNotAllowedException::allowedMethods()` returns the allowed method list.

Use the low-level match and dispatch APIs when you need to inspect the route before execution:

```php
$match = $router->match($request);

$route = $match->route();
$params = $match->params();
$method = $match->method();

$response = (new Dispatcher())->dispatch($request, $match, $container);
```

`Router::match()` returns a `RouteMatch`. It does not mutate the matched route, so route parameters stay request-local.

## Middleware, before handlers, and after handlers

Add PSR-15 middleware to the dispatcher, a group, or a route:

```php
$dispatcher = new Dispatcher();
$dispatcher->middleware($sessionMiddleware);

$router->group('/admin', function (Group $admin) use ($authMiddleware): void {
    $admin->middleware($authMiddleware);
    $admin->get('', [AdminController::class, 'index']);
});

$router->get('/reports', [ReportController::class, 'index'])
    ->middleware($auditMiddleware);
```

Use `Before` handlers for final request changes before the view runs:

```php
use Duon\Router\Before;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AddRequestId implements Before
{
    public function handle(Request $request): Request
    {
        return $request->withAttribute('requestId', bin2hex(random_bytes(8)));
    }

    public function replace(Before $handler): bool
    {
        return $handler instanceof self;
    }
}
```

Use `After` handlers to render arbitrary view data or modify a response:

```php
use Duon\Router\After;
use Psr\Http\Message\ResponseInterface as Response;

final class JsonRenderer implements After
{
    public function handle(mixed $data): Response
    {
        $response = $this->responseFactory->createResponse();
        $response->getBody()->write(json_encode($data, JSON_THROW_ON_ERROR));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function replace(After $handler): bool
    {
        return $handler instanceof self;
    }
}
```

Handlers are merged by calling `replace()` on existing handlers. Return `true` to replace a previous handler of the same kind; return `false` to append.

You can also place middleware, `Before`, and `After` instances as PHP attributes on route callables or controller methods:

```php
#[AuditMiddleware]
#[JsonRenderer]
function albumData(int $id): array
{
    return ['id' => $id];
}
```

Attribute handlers are resolved when the view runs. Middleware attributes run after route middleware; `Before` and `After` attributes are merged with dispatcher and route handlers.

Middleware and handlers run in this order:

1. Dispatcher middleware
2. Route middleware
3. View middleware attributes
4. `Before` handlers immediately before the view
5. View execution
6. `After` handlers for rendering or response changes

## View arguments and return values

Route parameters are passed to view arguments by name:

```php
$router->get('/albums/{id:\d+}', function (int $id) use ($responseFactory) {
    $response = $responseFactory->createResponse();
    $response->getBody()->write((string) $id);

    return $response;
});
```

Views can type-hint the PSR-7 request or the matched route:

```php
use Duon\Router\Route;
use Psr\Http\Message\ServerRequestInterface as Request;

$router->get('/debug', function (Request $request, Route $route) use ($responseFactory) {
    return $responseFactory->createResponse(204);
});
```

Other class or interface typed arguments are autowired through `duon/wire`. If a parameter has a default value, the default is used when that argument cannot be resolved.

A view must return a PSR-7 response or a `ResponseWrapper`. If a view returns arbitrary data, add an `After` handler that converts that data to a response.

## URL generation

Use `Router::url()` for named routes. It applies the router prefix, appends query params, and validates path params before returning a URL.

```php
$router = new Router('/cms');
$router->get('/albums/{id:\d+}', $view, 'albums.show');

$router->url('albums.show', ['id' => 13]);
// /cms/albums/13

$router->url('albums.show', ['id' => 13], query: ['tab' => 'tracks']);
// /cms/albums/13?tab=tracks

$router->url('albums.show', ['id' => 13], host: 'https://duon.sh');
// https://duon.sh/cms/albums/13
```

Strict URL generation throws `InvalidArgumentException` when:

- a path parameter is missing
- `params` contains an unknown route parameter
- a path parameter is not scalar or `Stringable`
- a path parameter does not match its route constraint
- a remainder parameter would create an absolute or parent-relative path
- a query parameter is not scalar or a list of scalars

Put non-path values in `query` instead of `params`:

```php
$router->get('/albums', [AlbumController::class, 'index'], 'albums.index');

$router->url('albums.index', query: ['page' => 2, 'tag' => ['death', 'thrash']]);
// /albums?page=2&tag%5B0%5D=death&tag%5B1%5D=thrash
```

`null` query values are skipped.

## Static assets

Static assets use `Router::asset()` so asset paths stay separate from named routes.

```php
$router->addStatic('/assets', __DIR__ . '/public/assets', 'assets');

$router->asset('assets', 'app.css');
// /assets/app.css

$router->asset('assets', 'app.css', bust: true);
// /assets/app.css?v=...

$router->asset('assets', 'app.css', host: 'https://cdn.duon.sh');
// https://cdn.duon.sh/assets/app.css
```

`asset()` rejects null bytes and `..` path segments so generated asset paths stay inside the static root. Cache busting adds a `v` query parameter based on the file modification time when the target file exists inside the static directory.

## Public API

Use these classes and interfaces in application code:

- `Router`
- `Route`
- `RouteMatch`
- `Group`
- `Dispatcher`
- `RoutingHandler`
- `Before`
- `After`
- route exceptions such as `NotFoundException`, `MethodNotAllowedException`, `InvalidArgumentException`, and `RuntimeException`

Most applications only need `Router`, `Group`, `Dispatcher`, and `RoutingHandler`. Classes marked `@internal` are implementation details and may change without notice.
