Duon Router
============

A PSR-7 compatible router and view dispatcher.

```php
<?php
$router = new Router();
$router->get('/{name}', funtion (string $name) { return "<h1>{$name}</h1>"; });
$request = new ServerRequest();
$route = $router->match($request);
$dispatcher = new Dispatcher();
$response = $dispatcher->dispatch($request, $route);
```
