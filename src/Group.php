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
	private bool $collecting = false;
	private bool $finalizing = false;

	/**
	 * Groups are callback-scoped. Use Router::group() or nested Group::group()
	 * so the router controls registration, ordering, and group finalization.
	 */
	private function __construct(
		private string $patternPrefix,
		private Closure $createClosure,
		private string $namePrefix = '',
	) {}

	/**
	 * Advanced escape hatch for route providers that must build a detached group.
	 * Prefer Router::group() for normal route definitions.
	 *
	 * Order matters: define routes inside the make() callback, then register the
	 * group with a router or parent group. Calling route helpers after register()
	 * throws because group finalization already happened.
	 *
	 * Example:
	 *
	 *     $group = Group::make('/api', static function (Group $api): void {
	 *         $api->get('/health', static fn() => 'ok', 'health');
	 *     }, 'api.');
	 *     $group->register($router);
	 *
	 * @internal
	 */
	public static function make(
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
			$this->assertCollecting('Cannot add routes outside the group callback.');
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
		$group = self::make($patternPrefix, $createClosure, $namePrefix);

		if ($this->routeAdder === null) {
			throw new RuntimeException('RouteAdder not set');
		}

		if (!$this->finalizing) {
			$this->assertCollecting('Cannot add groups outside the group callback.');
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
		$this->collecting = true;

		try {
			($this->createClosure)($this);
		} finally {
			$this->collecting = false;
		}

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

	private function assertCollecting(string $message): void
	{
		if (!$this->collecting) {
			throw new RuntimeException($message);
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
