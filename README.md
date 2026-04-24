# Duon Router

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE.md)
[![Codacy Badge](https://app.codacy.com/project/badge/Grade/715bb87b01ed458182a2d3af1cf6f4ba)](https://app.codacy.com/gh/duoncode/router/dashboard?utm_source=gh&utm_medium=referral&utm_content=&utm_campaign=Badge_grade)
[![Codacy Badge](https://app.codacy.com/project/badge/Coverage/715bb87b01ed458182a2d3af1cf6f4ba)](https://app.codacy.com/gh/duoncode/router/dashboard?utm_source=gh&utm_medium=referral&utm_content=&utm_campaign=Badge_coverage)
[![Psalm level](https://shepherd.dev/github/duoncode/router/level.svg?)](https://duon.sh/router)
[![Psalm coverage](https://shepherd.dev/github/duoncode/router/coverage.svg?)](https://shepherd.dev/github/duoncode/router)

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

## License

This project is licensed under the [MIT license](LICENSE.md).
