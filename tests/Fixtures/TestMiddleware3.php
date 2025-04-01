<?php

declare(strict_types=1);

namespace Duon\Router\Tests\Fixtures;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as Handler;

class TestMiddleware3 implements Middleware
{
	public function process(Request $request, Handler $handler): Response
	{
		return $handler->handle($request->withAttribute(
			'mw3',
			'Middleware 3' . ($request->getAttribute('mw2', '') ? ' - After 2' : ''),
		));
	}
}