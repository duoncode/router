<?php

declare(strict_types=1);

namespace Duon\Router\Tests;

use Duon\Router\Dispatcher;
use Duon\Router\Exception\MethodNotAllowedException;
use Duon\Router\Exception\NotFoundException;
use Duon\Router\Router;
use Duon\Router\RoutingHandler;
use Duon\Router\Tests\Fixtures\TestBeforeFirst;
use Duon\Router\Tests\Fixtures\TestBeforeSecond;
use Duon\Router\Tests\Fixtures\TestMiddleware1;
use Duon\Router\Tests\Fixtures\TestMiddleware2;
use Duon\Router\Tests\Fixtures\TestMiddleware3;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;

final class RoutingHandlerTest extends TestCase
{
	public function testImplementsRequestHandler(): void
	{
		$handler = new RoutingHandler(new Router(), new Dispatcher());

		$this->assertInstanceOf(RequestHandlerInterface::class, $handler);
	}

	public function testHandleDispatchesMatchedRouteWithParams(): void
	{
		$router = new Router();
		$router->get('/albums/{name}', static fn(string $name): string => $name)
			->after($this->renderer());
		$handler = new RoutingHandler($router, new Dispatcher());

		$response = $handler->handle($this->request('GET', '/albums/symbolic'));

		$this->assertSame('symbolic', (string) $response->getBody());
	}

	public function testHandleCanBeRepeatedWithMiddleware(): void
	{
		$router = new Router();
		$router
			->get(
				'/albums/{name}',
				static fn(Request $request, string $name): string => (
					$request->getAttribute('mw1') . '|' . $name
				),
			)
			->middleware(new TestMiddleware1())
			->after($this->renderer());
		$handler = new RoutingHandler($router, new Dispatcher());

		$first = $handler->handle($this->request('GET', '/albums/symbolic'));
		$second = $handler->handle($this->request('GET', '/albums/leprosy'));

		$this->assertSame('Middleware 1|symbolic', (string) $first->getBody());
		$this->assertSame('Middleware 1|leprosy', (string) $second->getBody());
	}

	public function testGlobalMiddlewareWrapsRouteMiddleware(): void
	{
		$router = new Router();
		$router
			->get(
				'/',
				static fn(Request $request): string => (
					$request->getAttribute('mw1')
					. '|'
					. $request->getAttribute('mw2')
					. '|'
					. $request->getAttribute('mw3')
				),
			)
			->middleware(new TestMiddleware2())
			->middleware(new TestMiddleware3())
			->after($this->renderer());
		$dispatcher = new Dispatcher();
		$dispatcher->middleware(new TestMiddleware1());
		$handler = new RoutingHandler($router, $dispatcher);

		$response = $handler->handle($this->request('GET', '/'));

		$this->assertSame(
			'Middleware 1|Middleware 2 - After 1|Middleware 3 - After 2',
			(string) $response->getBody(),
		);
	}

	public function testBeforeHandlersRunBeforeView(): void
	{
		$router = new Router();
		$router->get(
			'/',
			static fn(Request $request): string => (
				$request->getAttribute('first') . '|' . $request->getAttribute('second')
			),
		);
		$dispatcher = new Dispatcher();
		$dispatcher
			->before(new TestBeforeFirst())
			->before(new TestBeforeSecond())
			->after($this->renderer());
		$handler = new RoutingHandler($router, $dispatcher);

		$response = $handler->handle($this->request('GET', '/'));

		$this->assertSame('first-value-added-by-second|second-value', (string) $response->getBody());
	}

	public function testAfterHandlersRenderViewData(): void
	{
		$router = new Router();
		$router->get('/', static fn(): string => 'duon');
		$dispatcher = new Dispatcher();
		$dispatcher->after($this->renderer());
		$handler = new RoutingHandler($router, $dispatcher);

		$response = $handler->handle($this->request('GET', '/'));

		$this->assertSame('duon', (string) $response->getBody());
	}

	public function testNotFoundBubbles(): void
	{
		$this->throws(NotFoundException::class);

		$handler = new RoutingHandler(new Router(), new Dispatcher());

		$handler->handle($this->request('GET', '/missing'));
	}

	public function testMethodNotAllowedBubblesWithAllowedMethods(): void
	{
		$router = new Router();
		$router->get('/albums', static fn(): string => 'albums');
		$handler = new RoutingHandler($router, new Dispatcher());

		try {
			$handler->handle($this->request('POST', '/albums'));
			$this->fail('Expected MethodNotAllowedException to bubble.');
		} catch (MethodNotAllowedException $e) {
			$this->assertSame(['GET'], $e->allowedMethods());
		}
	}
}
