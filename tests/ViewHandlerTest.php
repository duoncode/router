<?php

declare(strict_types=1);

namespace Duon\Router\Tests;

use Duon\Router\Exception\RuntimeException;
use Duon\Router\ResponseWrapper;
use Duon\Router\Route;
use Duon\Router\Tests\Fixtures\TestController;
use Duon\Router\View;
use Duon\Router\ViewHandler;
use Laminas\Diactoros\ResponseFactory;
use Psr\Http\Message\ResponseInterface as Response;

class ViewHandlerTest extends TestCase
{
	public function testViewResponse(): void
	{
		$route = new Route('/', function () {
			$response = $this->responseFactory()->createResponse()
				->withHeader('Content-Type', 'text/plain');
			$response->getBody()->write('Duon PSR Response');

			return $response;
		});
		$route->match('/');
		$view = new View($route, null);
		$handler = new ViewHandler($view, [], []);
		$response = $handler->handle($this->request());

		$this->assertSame('Duon PSR Response', (string) $response->getBody());
		$this->assertSame('text/plain', $response->getHeaders()['Content-Type'][0]);
	}

	public function testViewResponseWrapper(): void
	{
		$route = new Route('/', function () {
			return new class ($this->responseFactory()) implements ResponseWrapper {
				public function __construct(protected ResponseFactory $factory) {}

				public function unwrap(): Response
				{
					$response = $this->factory->createResponse()
						->withHeader('Content-Type', 'text/plain');
					$response->getBody()->write('Duon PSR Response');

					return $response;
				}
			};
		});
		$route->match('/');
		$view = new View($route, null);
		$handler = new ViewHandler($view, [], []);
		$response = $handler->handle($this->request());

		$this->assertSame('Duon PSR Response', (string) $response->getBody());
		$this->assertSame('text/plain', $response->getHeaders()['Content-Type'][0]);
	}

	public function testWrongViewReturnType(): void
	{
		$this->throws(RuntimeException::class, 'Unable to determine a response handler');

		$route = new Route('/', TestController::class . '::wrongReturnType');
		$view = new View($route, null);
		$handler = new ViewHandler($view, [], []);
		$handler->handle($this->request());
	}
}