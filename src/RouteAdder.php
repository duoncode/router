<?php

declare(strict_types=1);

namespace Duon\Router;

use Duon\Router\Group;
use Duon\Router\Route;

/**
 * @psalm-api
 *
 * @psalm-import-type View from \Duon\Router\Route
 */
interface RouteAdder
{
	public function addRoute(Route $route): Route;

	public function addGroup(Group $group): void;

	/** @psalm-param View $view */
	public function route(string $pattern, callable|array|string $view, string $name = ''): Route;

	/** @psalm-param View $view */
	public function get(string $pattern, callable|array|string $view, string $name = ''): Route;

	/** @psalm-param View $view */
	public function post(string $pattern, callable|array|string $view, string $name = ''): Route;

	/** @psalm-param View $view */
	public function put(string $pattern, callable|array|string $view, string $name = ''): Route;

	/** @psalm-param View $view */
	public function patch(string $pattern, callable|array|string $view, string $name = ''): Route;

	/** @psalm-param View $view */
	public function delete(string $pattern, callable|array|string $view, string $name = ''): Route;

	/** @psalm-param View $view */
	public function head(string $pattern, callable|array|string $view, string $name = ''): Route;

	/** @psalm-param View $view */
	public function options(string $pattern, callable|array|string $view, string $name = ''): Route;

	/** @psalm-param class-string $controller */
	public function endpoint(array|string $path, string $controller, string|array $args): Endpoint;
}