<?php

declare(strict_types=1);

namespace Duon\Router;

/** @psalm-require-implements RouteAdder */
trait AddsRoutes
{
	abstract public function addRoute(Route $route): Route;

	/** @param callable|list{string, string}|non-empty-string $view */
	public function any(string $pattern, callable|array|string $view, string $name = ''): Route
	{
		return $this->addRoute(Route::any($pattern, $view, $name));
	}

	/** @param callable|list{string, string}|non-empty-string $view */
	public function get(string $pattern, callable|array|string $view, string $name = ''): Route
	{
		return $this->addRoute(Route::get($pattern, $view, $name));
	}

	/** @param callable|list{string, string}|non-empty-string $view */
	public function post(string $pattern, callable|array|string $view, string $name = ''): Route
	{
		return $this->addRoute(Route::post($pattern, $view, $name));
	}

	/** @param callable|list{string, string}|non-empty-string $view */
	public function put(string $pattern, callable|array|string $view, string $name = ''): Route
	{
		return $this->addRoute(Route::put($pattern, $view, $name));
	}

	/** @param callable|list{string, string}|non-empty-string $view */
	public function patch(string $pattern, callable|array|string $view, string $name = ''): Route
	{
		return $this->addRoute(Route::patch($pattern, $view, $name));
	}

	/** @param callable|list{string, string}|non-empty-string $view */
	public function delete(string $pattern, callable|array|string $view, string $name = ''): Route
	{
		return $this->addRoute(Route::delete($pattern, $view, $name));
	}

	/** @param callable|list{string, string}|non-empty-string $view */
	public function head(string $pattern, callable|array|string $view, string $name = ''): Route
	{
		return $this->addRoute(Route::head($pattern, $view, $name));
	}

	/** @param callable|list{string, string}|non-empty-string $view */
	public function options(string $pattern, callable|array|string $view, string $name = ''): Route
	{
		return $this->addRoute(Route::options($pattern, $view, $name));
	}

	/** @param class-string $controller */
	public function endpoint(array|string $path, string $controller, string|array $args): Endpoint
	{
		return new Endpoint($this, $path, $controller, $args);
	}
}
