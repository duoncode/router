<?php

declare(strict_types=1);

namespace Duon\Router;

/**
 * @psalm-import-type View from \Duon\Router\Route
 */
trait AddsRoutes
{
	abstract public function addRoute(Route $route): Route;

	/** @psalm-param View $view */
	public function route(string $pattern, callable|array|string $view, string $name = ''): Route
	{
		$route = new Route($pattern, $view, $name);
		$this->addRoute($route);

		return $route;
	}

	/** @psalm-param View $view */
	public function get(string $pattern, callable|array|string $view, string $name = ''): Route
	{
		return $this->addRoute(Route::get($pattern, $view, $name));
	}

	/** @psalm-param View $view */
	public function post(string $pattern, callable|array|string $view, string $name = ''): Route
	{
		return $this->addRoute(Route::post($pattern, $view, $name));
	}

	/** @psalm-param View $view */
	public function put(string $pattern, callable|array|string $view, string $name = ''): Route
	{
		return $this->addRoute(Route::put($pattern, $view, $name));
	}

	/** @psalm-param View $view */
	public function patch(string $pattern, callable|array|string $view, string $name = ''): Route
	{
		return $this->addRoute(Route::patch($pattern, $view, $name));
	}

	/** @psalm-param View $view */
	public function delete(string $pattern, callable|array|string $view, string $name = ''): Route
	{
		return $this->addRoute(Route::delete($pattern, $view, $name));
	}

	/** @psalm-param View $view */
	public function head(string $pattern, callable|array|string $view, string $name = ''): Route
	{
		return $this->addRoute(Route::head($pattern, $view, $name));
	}

	/** @psalm-param View $view */
	public function options(string $pattern, callable|array|string $view, string $name = ''): Route
	{
		return $this->addRoute(Route::options($pattern, $view, $name));
	}

	/** @psalm-param class-string $controller */
	public function endpoint(array|string $path, string $controller, string|array $args): Endpoint
	{
		/** @var RouteAdder $this */
		return new Endpoint($this, $path, $controller, $args);
	}
}