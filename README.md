# Duon Router

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE.md)
[![Codacy Badge](https://app.codacy.com/project/badge/Grade/715bb87b01ed458182a2d3af1cf6f4ba)](https://app.codacy.com/gh/duoncode/router/dashboard?utm_source=gh&utm_medium=referral&utm_content=&utm_campaign=Badge_grade)
[![Codacy Badge](https://app.codacy.com/project/badge/Coverage/715bb87b01ed458182a2d3af1cf6f4ba)](https://app.codacy.com/gh/duoncode/router/dashboard?utm_source=gh&utm_medium=referral&utm_content=&utm_campaign=Badge_coverage)
[![Psalm level](https://shepherd.dev/github/duoncode/router/level.svg?)](https://duon.sh/router)
[![Psalm coverage](https://shepherd.dev/github/duoncode/router/coverage.svg?)](https://shepherd.dev/github/duoncode/router)

A PSR-7/PSR-15 compatible router and view dispatcher.

Using your PSR-7 request and response factory:

```php
<?php

use Duon\Router\Dispatcher;
use Duon\Router\Router;
use Duon\Router\RoutingHandler;

$router = new Router();
$router->get('/{name}', function (string $name) use ($responseFactory) {
	$response = $responseFactory->createResponse();
	$response->getBody()->write("<h1>{$name}</h1>");

	return $response;
});

$handler = new RoutingHandler($router, new Dispatcher());
$response = $handler->handle($request);
```

## Dispatching

`RoutingHandler` implements PSR-15 `RequestHandlerInterface`. It matches the request through the router and dispatches the matched route through the dispatcher.

Routing exceptions bubble by default. Catch `NotFoundException` for 404 responses and `MethodNotAllowedException` for 405 responses.

Use the low-level match API when you need route inspection before dispatching:

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

Use named routes through the router. Generated URLs include the router prefix and fail fast when path params are missing, unknown, or do not match route constraints.

```php
$router = new Router('/cms');
$router->get('/albums/{id:\d+}', $view, 'albums.show');

$url = $router->url(
	'albums.show',
	['id' => 13],
	query: ['tab' => 'tracks'],
);
// /cms/albums/13?tab=tracks
```

Static assets stay separate from named routes:

```php
$router->addStatic('/assets', __DIR__ . '/public/assets', 'assets');

$css = $router->asset('assets', 'app.css', bust: true);
```

## License

This project is licensed under the [MIT license](LICENSE.md).
