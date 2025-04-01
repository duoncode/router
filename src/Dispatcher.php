<?php

declare(strict_types=1);

namespace Duon\Router;

use Duon\Router\View;
use Duon\Router\ViewHandler;
use Psr\Container\ContainerInterface as Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class Dispatcher
{
	use AddsBeforeAfter;
	use AddsMiddleware;

	public function dispatch(Request $request, Route $route, ?Container $container = null): Response
	{
		$view = new View($route, $container, $this->beforeHandlers, $this->afterHandlers);
		$handler = new ViewHandler($view, $this->middleware);

		return $handler->handle($request);
	}
}