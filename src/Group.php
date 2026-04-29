<?php

declare(strict_types=1);

namespace Duon\Router;

use Closure;
use Duon\Router\Exception\RuntimeException;
use Override;
use Psr\Http\Server\MiddlewareInterface as Middleware;

/** @psalm-api */
final class Group implements RouteAdder
{
	use AddsBeforeAfter {
		before as private addBeforeHandler;
		after as private addAfterHandler;
	}
	use AddsMiddleware {
		middleware as private addMiddlewareHandlers;
	}
	use AddsRoutes;

	/** @var list<Route|Group> */
	private array $entries = [];

	private ?RouteAdder $routeAdder = null;
	private ?string $controller = null;
	private bool $registered = false;
	private bool $collecting = false;

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
		$this->assertCollecting();
		$this->controller = $controller;

		return $this;
	}

	public function middleware(Middleware ...$middleware): static
	{
		$this->assertCollecting();

		return $this->addMiddlewareHandlers(...$middleware);
	}

	public function before(Before $beforeHandler): static
	{
		$this->assertCollecting();

		return $this->addBeforeHandler($beforeHandler);
	}

	public function after(After $afterHandler): static
	{
		$this->assertCollecting();

		return $this->addAfterHandler($afterHandler);
	}

	#[Override]
	public function addRoute(Route $route): Route
	{
		$this->assertCollecting();
		$this->entries[] = $route;

		return $route;
	}

	#[Override]
	public function group(
		string $patternPrefix,
		Closure $createClosure,
		string $namePrefix = '',
	): void {
		$this->assertCollecting();
		$this->entries[] = self::make($patternPrefix, $createClosure, $namePrefix);
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

		foreach ($this->entries as $entry) {
			if ($entry instanceof Route) {
				$this->forwardRoute($entry);
			} else {
				$entry->register($this);
			}
		}
	}

	private function assertCollecting(): void
	{
		if (!$this->collecting) {
			throw new RuntimeException('Cannot modify group outside the group callback.');
		}
	}

	private function receive(Route $route): Route
	{
		return $this->forwardRoute($route);
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

		if ($this->routeAdder instanceof self) {
			return $this->routeAdder->receive($route);
		}

		return $this->routeAdder->addRoute($route);
	}
}
