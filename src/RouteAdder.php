<?php

declare(strict_types=1);

namespace Duon\Router;

/** @psalm-api */
interface RouteAdder
{
	public function addRoute(Route $route): Route;

	public function group(
		string $patternPrefix,
		\Closure $createClosure,
		string $namePrefix = '',
	): void;

	/** @param callable|list{string, string}|non-empty-string $view */
	public function any(string $pattern, callable|array|string $view, string $name = ''): Route;

	/**
	 * @param array<array-key, string> $methods
	 * @param callable|list{string, string}|non-empty-string $view
	 */
	public function map(
		array $methods,
		string $pattern,
		callable|array|string $view,
		string $name = '',
	): Route;

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
}
