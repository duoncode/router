<?php

declare(strict_types=1);

namespace Duon\Router;

use Closure;
use Duon\Router\Exception\InvalidArgumentException;
use Duon\Router\Exception\ValueError;
use Stringable;

/**
 * @psalm-api
 *
 * @psalm-type View = callable|list{string, string}|non-empty-string
 */
class Route
{
	use AddsBeforeAfter;
	use AddsMiddleware;

	/** @psalm-var null|list<string> */
	protected ?array $methods = null;

	/** @psalm-var Closure|list{string, string}|string */
	protected Closure|array|string $view;

	protected ?RoutePattern $routePattern = null;

	/**
	 * @param string $pattern The URL pattern of the route
	 *
	 * @psalm-param View $view The callable view. Can be a closure, an invokable object or any other callable
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

	/** @psalm-param View $view */
	public static function any(string $pattern, callable|array|string $view, string $name = ''): self
	{
		return new self($pattern, $view, $name);
	}

	/** @psalm-param View $view */
	public static function get(string $pattern, callable|array|string $view, string $name = ''): self
	{
		return new self($pattern, $view, $name)->method('GET');
	}

	/** @psalm-param View $view */
	public static function post(string $pattern, callable|array|string $view, string $name = ''): self
	{
		return new self($pattern, $view, $name)->method('POST');
	}

	/** @psalm-param View $view */
	public static function put(string $pattern, callable|array|string $view, string $name = ''): self
	{
		return new self($pattern, $view, $name)->method('PUT');
	}

	/** @psalm-param View $view */
	public static function patch(string $pattern, callable|array|string $view, string $name = ''): self
	{
		return new self($pattern, $view, $name)->method('PATCH');
	}

	/** @psalm-param View $view */
	public static function delete(
		string $pattern,
		callable|array|string $view,
		string $name = '',
	): self {
		return new self($pattern, $view, $name)->method('DELETE');
	}

	/** @psalm-param View $view */
	public static function head(string $pattern, callable|array|string $view, string $name = ''): self
	{
		return new self($pattern, $view, $name)->method('HEAD');
	}

	/** @psalm-param View $view */
	public static function options(
		string $pattern,
		callable|array|string $view,
		string $name = '',
	): self {
		return new self($pattern, $view, $name)->method('OPTIONS');
	}

	/** @no-named-arguments */
	public function method(string ...$args): static
	{
		$this->methods = array_merge($this->methods ?? [], array_map(static fn($m) => strtoupper(
			$m,
		), $args));

		return $this;
	}

	/** @psalm-return list<string> */
	public function methods(): array
	{
		return $this->methods ?? [];
	}

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

	/**
	 * Simply prefixes the current $this->view string with $controller.
	 */
	public function controller(string $controller): static
	{
		if (is_string($this->view)) {
			$this->view = [$controller, $this->view];

			return $this;
		}

		throw new ValueError(
			'Cannot add controller to view of type Closure or array. '
			. 'Also, Endpoints cannot be used in a Group which utilises controllers',
		);
	}

	public function name(): string
	{
		return $this->name;
	}

	/**
	 * @psalm-suppress MixedAssignment
	 *
	 * Types are checked in the body.
	 */
	public function url(mixed ...$args): string
	{
		$url = '/' . ltrim($this->pattern, '/');

		if (count($args) > 0) {
			if (is_array($args[0] ?? null)) {
				$args = $args[0];
			} else {
				// Check if args is an associative array
				if (array_keys($args) === range(0, count($args) - 1)) {
					throw new InvalidArgumentException(
						'Route::url: either pass an associative array or named arguments',
					);
				}
			}

			/**
			 * @psalm-suppress MixedAssignment
			 *
			 * We check if $value can be transformed into a string, Psalm
			 * complains anyway.
			 */
			foreach ($args as $name => $value) {
				// TODO: throw error if args do not match url params
				if (is_scalar($value) or $value instanceof Stringable) {
					// basic variables
					$replaced = preg_replace(
						'/\{' . (string) $name . '(:.*?)?\}/',
						urlencode((string) $value),
						$url,
					);
					$url = $replaced ?? $url;

					// remainder variables
					$replaced = preg_replace(
						'/\.\.\.' . (string) $name . '/',
						urlencode((string) $value),
						$url,
					);
					$url = $replaced ?? $url;
				} else {
					throw new InvalidArgumentException('No valid url argument');
				}
			}
		}

		return $url;
	}

	/** @psalm-return Closure|list{string, string}|string */
	public function view(): Closure|array|string
	{
		return $this->view;
	}

	public function pattern(): string
	{
		return $this->pattern;
	}

	public function match(string $url, string $prefix = ''): ?Route
	{
		return $this->matchPath($url, $prefix) === null ? null : $this;
	}

	/** @return null|array<string, string> */
	public function matchPath(string $url, string $prefix = ''): ?array
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
