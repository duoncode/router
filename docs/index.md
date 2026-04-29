---
title: Introduction
---

# Duon Router

Duon Router is a PSR-7/PSR-15 router and view dispatcher. `RoutingHandler` implements PSR-15 `RequestHandlerInterface` and is the primary runtime entry point.

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

## Routes

Define routes with HTTP method helpers. Use `any()` when a route intentionally accepts every request method.

```php
$router->get('/health', [HealthController::class, 'show'], 'health');
$router->post('/albums', [AlbumController::class, 'create'], 'albums.create');
$router->any('/webhook', $webhook, 'webhook');
```

### Actions

Preferred route actions are callables and controller method arrays:

```php
$router->get('/status', fn() => $response);
$router->get('/albums', [AlbumController::class, 'index']);
```

Inside a controller group, route actions can be bare method names. Outside a controller group, use a callable or `[Controller::class, 'method']`.

### Groups

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

## Dispatching

`RoutingHandler` lets `NotFoundException` and `MethodNotAllowedException` bubble by default so your application can render 404 and 405 responses. `MethodNotAllowedException::allowedMethods()` returns the allowed method list.

Use the low-level match and dispatch APIs when you need to inspect the route before execution:

```php
$match = $router->match($request);
$response = (new Dispatcher())->dispatch($request, $match);
```

Middleware and handlers run in this order:

1. Dispatcher middleware
2. Route middleware
3. View middleware attributes
4. `Before` handlers immediately before the view
5. View execution
6. `After` handlers for rendering or response changes

Use PSR middleware for cross-cutting request/response behavior. Use `Before` for final request changes before the view. Use `After` to render arbitrary view data or modify a response.

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

Route patterns support these path tokens:

- `{name}` matches one default path segment and must be generated with a matching param
- `{id:\d+}` adds a regex constraint that is also checked during URL generation
- `...slug` captures the rest of the path and preserves slashes when generated

Put non-path values in `query` instead of `params`:

```php
$router->url('albums.index', query: ['page' => 2, 'tag' => ['death', 'thrash']]);
// /albums?page=2&tag%5B0%5D=death&tag%5B1%5D=thrash
```

## Static assets

Static assets use `Router::asset()` so asset paths stay separate from named routes.

```php
$router->addStatic('/assets', __DIR__ . '/public/assets', 'assets');

$router->asset('assets', 'app.css', bust: true);
// /assets/app.css?v=...
```
