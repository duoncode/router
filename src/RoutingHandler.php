<?php

declare(strict_types=1);

namespace Duon\Router;

use Override;
use Psr\Container\ContainerInterface as Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

/** @psalm-api */
final class RoutingHandler implements RequestHandler
{
	public function __construct(
		private readonly Router $router,
		private readonly Dispatcher $dispatcher,
		private readonly ?Container $container = null,
	) {}

	#[Override]
	public function handle(Request $request): Response
	{
		return $this->dispatcher->dispatch(
			$request,
			$this->router->match($request),
			$this->container,
		);
	}
}
