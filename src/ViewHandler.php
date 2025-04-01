<?php

declare(strict_types=1);

namespace Duon\Router;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class ViewHandler implements RequestHandler
{
	/** @param list<Middleware> $middleware */
	protected array $middleware;

	/**
	 * @param list<Middleware> $globalMiddleware
	 */
	public function __construct(
		protected readonly View $view,
		array $globalMiddleware,
	) {
		$this->middleware = array_merge($globalMiddleware, $view->middleware());
	}

	public function handle(Request $request): Response
	{
		if (0 === count($this->middleware)) {
			return $this->view->execute($request);
		}

		$middleware = array_shift($this->middleware);

		return $middleware->process($request, $this);
	}
}