<?php

declare(strict_types=1);

namespace Duon\Router;

/** @psalm-api */
interface RouteAdder
{
	public function addRoute(Route $route): Route;

	public function addGroup(Group $group): void;

	/** @param callable|list{string, string}|non-empty-string $view */
	public function route(string $pattern, callable|array|string $view, string $name = ''): Route;

	/** @param callable|list{string, string}|non-empty-string $view */
	public function get(string $pattern, callable|array|string $view, string $name = ''): Route;

	/** @param callable|list{string, string}|non-empty-string $view */
	public function post(string $pattern, callable|array|string $view, string $name = ''): Route;

	/** @param callable|list{string, string}|non-empty-string $view */
	public function put(string $pattern, callable|array|string $view, string $name = ''): Route;

	/** @param callable|list{string, string}|non-empty-string $view */
	public function patch(string $pattern, callable|array|string $view, string $name = ''): Route;

	/** @param callable|list{string, string}|non-empty-string $view */
	public function delete(string $pattern, callable|array|string $view, string $name = ''): Route;

	/** @param callable|list{string, string}|non-empty-string $view */
	public function head(string $pattern, callable|array|string $view, string $name = ''): Route;

	/** @param callable|list{string, string}|non-empty-string $view */
	public function options(string $pattern, callable|array|string $view, string $name = ''): Route;

	/** @param class-string $controller */
	public function endpoint(array|string $path, string $controller, string|array $args): Endpoint;
}
