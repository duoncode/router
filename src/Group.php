<?php

declare(strict_types=1);

namespace Duon\Router;

use Closure;
use Duon\Router\Exception\RuntimeException;
use Override;

/** @psalm-api */
class Group implements RouteAdder
{
	use AddsBeforeAfter;
	use AddsMiddleware;
	use AddsRoutes;

	/** @var list<Route|Group> */
	protected array $entries = [];

	protected ?RouteAdder $routeAdder = null;
	protected ?string $controller = null;
	protected bool $created = false;
	protected bool $finalizing = false;

	public function __construct(
		protected string $patternPrefix,
		protected Closure $createClosure,
		protected string $namePrefix = '',
	) {}

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
		$group = new Group($patternPrefix, $createClosure, $namePrefix);

		if ($this->routeAdder === null) {
			throw new RuntimeException('RouteAdder not set');
		}

		if (!$this->finalizing) {
			$this->entries[] = $group;

			return $group;
		}

		$group->create($this);

		return $group;
	}

	/** @internal */
	public function create(RouteAdder $adder): void
	{
		if ($this->created) {
			return;
		}

		$this->created = true;
		$this->routeAdder = $adder;
		($this->createClosure)($this);

		$this->finalizing = true;

		try {
			foreach ($this->entries as $entry) {
				if ($entry instanceof Route) {
					$this->forwardRoute($entry);
				} else {
					$entry->create($this);
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
