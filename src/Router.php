<?php

declare(strict_types=1);

namespace Duon\Router;

use Closure;
use Duon\Router\Exception\InvalidArgumentException;
use Duon\Router\Exception\MethodNotAllowedException;
use Duon\Router\Exception\NotFoundException;
use Duon\Router\Exception\RuntimeException;
use Override;
use Psr\Http\Message\ServerRequestInterface as Request;
use Stringable;

/** @psalm-api */
class Router implements RouteAdder
{
	use AddsRoutes;

	protected readonly string $globalPrefix;

	public function __construct(string $globalPrefix = '')
	{
		$globalPrefix = trim($globalPrefix, '/');
		$this->globalPrefix = $globalPrefix === '' ? '' : '/' . $globalPrefix;
	}

	protected const string ANY = 'ANY';

	protected string $cacheFile = '';
	protected bool $shouldCache = false;

	/** @var array<string, list<Route>> */
	protected array $routes = [];

	/** @var array<string, StaticRoute> */
	protected array $staticRoutes = [];

	/** @var array<string, Route> */
	protected array $names = [];

	/** @param Closure(Router): void $creator */
	public function routes(Closure $creator, string $cacheFile = '', bool $shouldCache = true): void
	{
		$this->cacheFile = $cacheFile;
		$this->shouldCache = $shouldCache;

		$creator($this);
	}

	#[Override]
	public function addRoute(Route $route): Route
	{
		$name = $route->name();
		$noMethodGiven = true;

		foreach ($route->methods() as $method) {
			$noMethodGiven = false;
			$this->routes[$method][] = $route;
		}

		if ($noMethodGiven) {
			$this->routes[self::ANY][] = $route;
		}

		if ($name) {
			if (array_key_exists($name, $this->names)) {
				throw new RuntimeException(
					'Duplicate route: '
					. $name
					. '. If     ||    you want to use the same '
					. 'url pattern with different methods, you have to create routes with names.',
				);
			}

			$this->names[$name] = $route;
		}

		return $route;
	}

	#[Override]
	public function addGroup(Group $group): void
	{
		$group->create($this);
	}

	public function addStatic(
		string $prefix,
		string $dir,
		string $name = '',
	): void {
		if ($name === '') {
			$name = $prefix;
		}

		if (array_key_exists($name, $this->staticRoutes)) {
			throw new RuntimeException(
				'Duplicate static route: '
				. $name
				. '. If you want to use the same '
				. 'url prefix you have to create static routes with names.',
			);
		}

		if (is_dir($dir)) {
			$this->staticRoutes[$name] = new StaticRoute(
				prefix: $this->globalPrefix . '/' . trim($prefix, '/') . '/',
				dir: $dir,
			);
		} else {
			throw new RuntimeException("The static directory does not exist: {$dir}");
		}
	}

	public function asset(
		string $name,
		string $path,
		bool $bust = false,
		?string $host = null,
	): string {
		$route = $this->staticRoutes[$name] ?? null;

		if (!$route) {
			throw new NotFoundException('Static route not found: ' . $name);
		}

		[$file, $hasQuery] = $this->splitStaticPath($path);
		$this->assertSafeStaticPath($file);

		if ($bust) {
			$buster = $this->getCacheBuster($route->dir, $file);

			if ($buster !== '') {
				$path .= ($hasQuery ? '&' : '?') . 'v=' . $buster;
			}
		}

		return $this->prependHost($route->prefix . trim($path, '/'), $host);
	}

	/**
	 * @param array<string, mixed> $params
	 * @param array<string, mixed> $query
	 */
	public function url(
		string $name,
		array $params = [],
		array $query = [],
		?string $host = null,
	): string {
		$route = $this->names[$name] ?? null;

		if (!$route) {
			throw new NotFoundException('Route not found: ' . $name);
		}

		$url = $this->applyGlobalPrefix($route->url($params));
		$queryString = $this->queryString($query);

		if ($queryString !== '') {
			$url .= '?' . $queryString;
		}

		return $this->prependHost($url, $host);
	}

	public function match(Request $request): RouteMatch
	{
		$url = rawurldecode($request->getUri()->getPath());
		$requestMethod = strtoupper($request->getMethod());

		foreach ([$requestMethod, self::ANY] as $method) {
			foreach ($this->routes[$method] ?? [] as $route) {
				$params = $route->match($url, $this->globalPrefix);

				if ($params !== null) {
					return new RouteMatch($route, $params, $requestMethod);
				}
			}
		}

		/** @var list<string> $allowedMethods */
		$allowedMethods = [];

		foreach ($this->routes as $method => $routes) {
			if ($method === $requestMethod || $method === self::ANY) {
				continue;
			}

			foreach ($routes as $route) {
				if ($route->match($url, $this->globalPrefix) === null) {
					continue;
				}

				$allowedMethods[] = $method;

				break;
			}
		}

		if (count($allowedMethods) > 0) {
			throw new MethodNotAllowedException($allowedMethods);
		}

		throw new NotFoundException();
	}

	private function applyGlobalPrefix(string $path): string
	{
		if ($this->globalPrefix === '') {
			return $path;
		}

		return $path === '/' ? $this->globalPrefix : $this->globalPrefix . $path;
	}

	private function prependHost(string $path, ?string $host): string
	{
		return $host === null ? $path : rtrim($host, '/') . $path;
	}

	/** @param array<string, mixed> $query */
	private function queryString(array $query): string
	{
		$normalized = $this->normalizeQuery($query);

		return http_build_query($normalized, '', '&', PHP_QUERY_RFC3986);
	}

	/**
	 * @param array<string, mixed> $query
	 * @return array<string, bool|int|float|string|list<bool|int|float|string>>
	 */
	private function normalizeQuery(array $query): array
	{
		$normalized = [];

		/** @psalm-suppress MixedAssignment -- query values are intentionally mixed and validated below */
		foreach ($query as $name => $value) {
			if ($value === null) {
				continue;
			}

			if (is_array($value)) {
				if (!array_is_list($value)) {
					throw new InvalidArgumentException(
						'Query parameter must be scalar or a list of scalars: ' . $name,
					);
				}

				$normalized[$name] = array_map(
					fn(mixed $item): bool|int|float|string => $this->queryValue($item, $name),
					$value,
				);

				continue;
			}

			$normalized[$name] = $this->queryValue($value, $name);
		}

		return $normalized;
	}

	private function queryValue(mixed $value, string $name): bool|int|float|string
	{
		if (is_scalar($value)) {
			return $value;
		}

		if ($value instanceof Stringable) {
			return (string) $value;
		}

		throw new InvalidArgumentException('Query parameter must be scalar or a list of scalars: '
		. $name);
	}

	protected function getCacheBuster(string $dir, string $path): string
	{
		$root = realpath($dir);

		if ($root === false) {
			return '';
		}

		$ds = DIRECTORY_SEPARATOR;
		$file = realpath($root . $ds . ltrim(str_replace('/', $ds, $path), $ds));

		if ($file === false || !$this->isInsideDirectory($file, $root)) {
			return '';
		}

		$mtime = filemtime($file);

		return $mtime === false ? '' : hash('xxh32', (string) $mtime);
	}

	/** @return array{string, bool} */
	private function splitStaticPath(string $path): array
	{
		$queryStart = strpos($path, '?');

		if ($queryStart === false) {
			return [$path, false];
		}

		return [substr($path, 0, $queryStart), true];
	}

	private function assertSafeStaticPath(string $path): void
	{
		$decodedPath = str_replace('\\', '/', rawurldecode($path));

		if (str_contains($decodedPath, "\0")) {
			throw new InvalidArgumentException('Static path must stay inside static root');
		}

		foreach (explode('/', $decodedPath) as $segment) {
			if ($segment === '..') {
				throw new InvalidArgumentException('Static path must stay inside static root');
			}
		}
	}

	private function isInsideDirectory(string $file, string $dir): bool
	{
		return str_starts_with($file, rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
	}
}
