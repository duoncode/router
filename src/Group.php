<?php

declare(strict_types=1);

namespace Duon\Router;

use Closure;
use Duon\Router\Exception\RuntimeException;
use Override;

/** @psalm-api */
final class Group implements RouteAdder
{
	use AddsBeforeAfter;
	use AddsMiddleware;
	use AddsRoutes;

	/** @var list<Route|Group> */
	private array $entries = [];

	private ?RouteAdder $routeAdder = null;
	private ?string $controller = null;
	private bool $registered = false;
	private bool $finalizing = false;

	private function __construct(
		private string $patternPrefix,
		private Closure $createClosure,
		private string $namePrefix = '',
	) {}

	/** @internal */
	public static function create(
		string $patternPrefix,
		Closure $createClosure,
		string $namePrefix = '',
	): self {
		return new self($patternPrefix, $createClosure, $namePrefix);
	}

	public function controller(string $controller): static
	{
		$this->controller = $controller;

		return $this;
	}

	#[Override]
	public function addRoute(Route $route): Route
	{
		if ($this->routeAdder === null) {
			throw new RuntimeException('RouteAdder not set');
		}

		if (!$this->finalizing) {
			$this->entries[] = $route;

			return $route;
		}

		return $this->forwardRoute($route);
	}

	#[Override]
	public function group(
		string $patternPrefix,
		Closure $createClosure,
		string $namePrefix = '',
	): Group {
		$group = self::create($patternPrefix, $createClosure, $namePrefix);

		if ($this->routeAdder === null) {
			throw new RuntimeException('RouteAdder not set');
		}

		if (!$this->finalizing) {
			$this->entries[] = $group;

			return $group;
		}

		$group->register($this);

		return $group;
	}

	/** @internal */
	public function register(RouteAdder $adder): void
	{
		if ($this->registered) {
			return;
		}

		$this->registered = true;
		$this->routeAdder = $adder;
		($this->createClosure)($this);

		$this->finalizing = true;

		try {
			foreach ($this->entries as $entry) {
				if ($entry instanceof Route) {
					$this->forwardRoute($entry);
				} else {
					$entry->register($this);
				}
			}
		} finally {
			$this->finalizing = false;
		}
	}

	private function forwardRoute(Route $route): Route
	{
		$route->prefix($this->patternPrefix, $this->namePrefix);

		if ($this->controller !== null) {
			$route->controller($this->controller);
		}

		$route->replaceMiddleware(array_merge($this->middleware, $route->getMiddleware()));
		$route->setBeforeHandlers($this->mergeBeforeHandlers($route->beforeHandlers()));
		$route->setAfterHandlers($this->mergeAfterHandlers($route->afterHandlers()));

		assert($this->routeAdder !== null, 'RouteAdder must be set before forwarding routes.');

		return $this->routeAdder->addRoute($route);
	}
}
