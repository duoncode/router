<?php

declare(strict_types=1);

namespace Duon\Router\Tests\Fixtures;

use Attribute;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as Handler;

#[Attribute]
class TestMiddleware1 implements Middleware
{
	public function process(Request $request, Handler $handler): Response
	{
		return $handler->handle($request->withAttribute('mw1', 'Middleware 1'));
	}
}