<?php

declare(strict_types=1);

namespace Duon\Router;

use Closure;
use Duon\Router\Exception\ValueError;

/**
 * @psalm-api
 *
 * @psalm-type View = callable|list{string, string}|non-empty-string
 */
class Route
{
	use AddsBeforeAfter;
	use AddsMiddleware;

	/** @var null|list<string> */
	protected ?array $methods = null;

	/** @var Closure|list{string, string}|string */
	protected Closure|array|string $view;

	protected ?RoutePattern $routePattern = null;

	/**
	 * @param string $pattern The URL pattern of the route
	 *
	 * @param callable|list{string, string}|non-empty-string $view The callable view. Can be a closure, an invokable object or any other callable
	 *
	 * @param string $name The name of the route. If not given the pattern will be hashed and used as name.
	 */
	public function __construct(
		protected string $pattern,
		callable|array|string $view,
		protected string $name = '',
	) {
		if (is_callable($view)) {
			$this->view = Closure::fromCallable($view);
		} else {
			$this->view = $view;
		}
	}

	/** @param callable|list{string, string}|non-empty-string $view */
	public static function any(string $pattern, callable|array|string $view, string $name = ''): self
	{
		return new self($pattern, $view, $name);
	}

	/**
	 * @param array<array-key, string> $methods
	 * @param callable|list{string, string}|non-empty-string $view
	 */
	public static function map(
		array $methods,
		string $pattern,
		callable|array|string $view,
		string $name = '',
	): self {
		if ($methods === []) {
			throw new ValueError('Route method list cannot be empty.');
		}

		return new self($pattern, $view, $name)->withMethods(...array_values($methods));
	}

	/** @param callable|list{string, string}|non-empty-string $view */
	public static function get(string $pattern, callable|array|string $view, string $name = ''): self
	{
		return new self($pattern, $view, $name)->withMethods('GET');
	}

	/** @param callable|list{string, string}|non-empty-string $view */
	public static function post(string $pattern, callable|array|string $view, string $name = ''): self
	{
		return new self($pattern, $view, $name)->withMethods('POST');
	}

	/** @param callable|list{string, string}|non-empty-string $view */
	public static function put(string $pattern, callable|array|string $view, string $name = ''): self
	{
		return new self($pattern, $view, $name)->withMethods('PUT');
	}

	/** @param callable|list{string, string}|non-empty-string $view */
	public static function patch(string $pattern, callable|array|string $view, string $name = ''): self
	{
		return new self($pattern, $view, $name)->withMethods('PATCH');
	}

	/** @param callable|list{string, string}|non-empty-string $view */
	public static function delete(
		string $pattern,
		callable|array|string $view,
		string $name = '',
	): self {
		return new self($pattern, $view, $name)->withMethods('DELETE');
	}

	/** @param callable|list{string, string}|non-empty-string $view */
	public static function head(string $pattern, callable|array|string $view, string $name = ''): self
	{
		return new self($pattern, $view, $name)->withMethods('HEAD');
	}

	/** @param callable|list{string, string}|non-empty-string $view */
	public static function options(
		string $pattern,
		callable|array|string $view,
		string $name = '',
	): self {
		return new self($pattern, $view, $name)->withMethods('OPTIONS');
	}

	private function withMethods(string ...$args): self
	{
		/** @var list<string> $methods */
		$methods = array_map(static fn(string $method): string => strtoupper($method), $args);
		$this->methods = [...($this->methods ?? []), ...$methods];

		return $this;
	}

	/** @return list<string> */
	public function methods(): array
	{
		return $this->methods ?? [];
	}

	/** @internal */
	public function prefix(string $pattern = '', string $name = ''): static
	{
		if ($pattern !== '') {
			$this->pattern = $pattern . $this->pattern;
			$this->routePattern = null;
		}

		if ($name !== '') {
			$this->name = $name . $this->name;
		}

		return $this;
	}

	/** @internal */
	public function controller(string $controller): static
	{
		if (is_string($this->view)) {
			$this->view = [$controller, $this->view];

			return $this;
		}

		throw new ValueError(
			'Cannot add controller to route action. Controller groups require bare method names.',
		);
	}

	public function name(): string
	{
		return $this->name;
	}

	/** @param array<array-key, mixed> $params */
	public function url(array $params = []): string
	{
		return $this->routePattern()->generate($params);
	}

	/** @return Closure|list{string, string}|string */
	public function view(): Closure|array|string
	{
		return $this->view;
	}

	public function pattern(): string
	{
		return $this->pattern;
	}

	/** @return null|array<string, string> */
	public function match(string $url, string $prefix = ''): ?array
	{
		$path = $this->pathWithoutPrefix($url, $prefix);

		if ($path === null) {
			return null;
		}

		return $this->routePattern()->match($path);
	}

	private function routePattern(): RoutePattern
	{
		return $this->routePattern ??= new RoutePattern($this->pattern);
	}

	private function pathWithoutPrefix(string $url, string $prefix): ?string
	{
		$path = $url === '' ? '/' : $url;
		$prefix = self::normalizePrefix($prefix);

		if ($prefix === '') {
			return $path;
		}

		if ($path === $prefix) {
			return '/';
		}

		if (!str_starts_with($path, $prefix . '/')) {
			return null;
		}

		$path = substr($path, strlen($prefix));

		if ($path === '/' && $this->routePattern()->pattern() === '/') {
			return null;
		}

		return $path;
	}

	private static function normalizePrefix(string $prefix): string
	{
		return $prefix === '' ? '' : '/' . trim($prefix, '/');
	}
}
