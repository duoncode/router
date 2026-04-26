---
title: Introduction
---

# Duon Route

!!! warning "Note"
    This library is under active development, some of the listed features are still experimental and subject to change. Large parts of the documentation are missing.

A router and view dispatcher. `Router::match()` returns an immutable `RouteMatch` with the matched route and path params; pass that match to `Dispatcher::dispatch()` to execute the view.

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
