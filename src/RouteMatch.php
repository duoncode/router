<?php

declare(strict_types=1);

namespace Duon\Router;

/** @psalm-api */
final readonly class RouteMatch
{
	private Route $route;

	/** @var array<string, string> */
	private array $params;

	private string $method;

	/** @param array<string, string> $params */
	public function __construct(Route $route, array $params, string $method)
	{
		$this->route = $route;
		$this->params = $params;
		$this->method = strtoupper($method);
	}

	public function route(): Route
	{
		return $this->route;
	}

	/** @return array<string, string> */
	public function params(): array
	{
		return $this->params;
	}

	public function method(): string
	{
		return $this->method;
	}
}
