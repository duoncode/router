<?php

declare(strict_types=1);

namespace Duon\Router;

use Psr\Http\Server\MiddlewareInterface as Middleware;

trait AddsMiddleware
{
	/** @var list<Middleware> */
	protected array $middleware = [];

	public function middleware(Middleware ...$middleware): static
	{
		$this->middleware = array_merge($this->middleware, array_values($middleware));

		return $this;
	}

	/** @psalm-return list<Middleware> */
	public function getMiddleware(): array
	{
		return $this->middleware;
	}

	/** @psalm-param list<Middleware> $middleware */
	public function replaceMiddleware(array $middleware): static
	{
		$this->middleware = $middleware;

		return $this;
	}
}