<?php

declare(strict_types=1);

use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\Request;
use Laminas\Diactoros\Uri;

use Duon\Router\Router;
use Duon\Router\Dispatcher;

$router = new Router();

$router->get('/', function () use ($factory) { return $factory->createResponse(200); });
$router->get('/:name', function (string $name) {
    $response = (new ResponseFactory())->createResponse(200);
    $response->getBody()->write($name);
    return $response;
});

$request = (new Request())->withUri(new Uri('http://example.com/duon-route'))->withMethod('GET');

$route = $router->match($request);

$dispatcher = new Dispatcher(new ResponseFactory(), new Renderers());

$response = $dispatcher->dispatch($route);

assert((string)$response->getBody() == 'duon-route');