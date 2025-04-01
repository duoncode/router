<?php

declare(strict_types=1);

namespace Duon\Router;

use Closure;
use Duon\Router\Exception\MethodNotAllowedException;
use Duon\Router\Exception\NotFoundException;
use Duon\Router\Exception\RuntimeException;
use Duon\Router\RouteAdder;
use Duon\Router\StaticRoute;
use Psr\Http\Message\ServerRequestInterface as Request;

/** @psalm-api */
class Router implements RouteAdder
{
	use AddsRoutes;

	protected readonly string $globalPrefix;

	public function __construct(string $globalPrefix = '')
	{
		$this->globalPrefix = $globalPrefix ? '/' . ltrim($globalPrefix, '/') : '';
	}

	protected const string ANY = 'ANY';

	protected string $cacheFile = '';
	protected bool $shouldCache = false;

	/** @psalm-var array<string, list<Route>> */
	protected array $routes = [];

	/** @var array<string, StaticRoute> */
	protected array $staticRoutes = [];

	/** @var array<string, Route> */
	protected array $names = [];

	/** @psalm-param Closure(Router $router):void $creator */
	public function routes(Closure $creator, string $cacheFile = '', bool $shouldCache = true): void
	{
		$this->cacheFile = $cacheFile;
		$this->shouldCache = $shouldCache;

		$creator($this);
	}

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
					'Duplicate route: ' . $name . '. If     ||    you want to use the same ' .
						'url pattern with different methods, you have to create routes with names.',
				);
			}

			$this->names[$name] = $route;
		}

		return $route;
	}

	public function addGroup(Group $group): void
	{
		$group->create($this);
	}

	public function addStatic(
		string $prefix,
		string $dir,
		string $name = '',
	): void {
		if (empty($name)) {
			$name = $prefix;
		}

		if (array_key_exists($name, $this->staticRoutes)) {
			throw new RuntimeException(
				'Duplicate static route: ' . $name . '. If you want to use the same ' .
					'url prefix you have to create static routes with names.',
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

	public function staticUrl(
		string $name,
		string $path,
		bool $bust = false,
		?string $host = null,
	): string {
		$route = $this->staticRoutes[$name];

		if ($bust) {
			// Check if there is already a query parameter present
			if (strpos($path, '?')) {
				$file = strtok($path, '?');
				$sep = '&';
			} else {
				$file = $path;
				$sep = '?';
			}

			$buster = $this->getCacheBuster($route->dir, $file);

			if (!empty($buster)) {
				$path .= $sep . 'v=' . $buster;
			}
		}

		return ($host ? trim($host, '/') : '') . $route->prefix . trim($path, '/');
	}

	public function routeUrl(string $__routeName__, mixed ...$args): string
	{
		$route = $this->names[$__routeName__] ?? null;

		if ($route) {
			return $route->url(...$args);
		}

		throw new NotFoundException('Route not found: ' . $__routeName__);
	}

	public function match(Request $request): Route
	{
		$url = rawurldecode($request->getUri()->getPath());
		$requestMethod = $request->getMethod();

		foreach ([$requestMethod, self::ANY] as $method) {
			foreach ($this->routes[$method] ?? [] as $route) {
				if ($route->match($url, $this->globalPrefix)) {
					return $route;
				}
			}
		}

		// We know now, that the route does not match.
		// Check if it would match one of the remaining methods
		$wrongMethod = false;
		$remainingMethods = array_keys($this->routes);

		foreach ([$requestMethod, self::ANY] as $method) {
			if (($key = array_search($method, $remainingMethods)) !== false) {
				unset($remainingMethods[$key]);
			}
		}

		foreach ($remainingMethods as $method) {
			foreach ($this->routes[$method] as $route) {
				if ($route->match($url, $this->globalPrefix)) {
					$wrongMethod = true;

					break;
				}
			}
		}

		if ($wrongMethod) {
			throw new MethodNotAllowedException();
		}

		throw new NotFoundException();
	}

	protected function getCacheBuster(string $dir, string $path): string
	{
		$ds = DIRECTORY_SEPARATOR;
		$file = realpath($dir . $ds . ltrim(str_replace('/', $ds, $path), $ds));

		if ($file) {
			return hash('xxh32', (string) filemtime($file)) ?? '';
		}

		return '';
	}
}