<?php

declare(strict_types=1);

namespace Duon\Router;

use Duon\Router\Exception\RuntimeException;

/** @psalm-api */
class Endpoint
{
	use AddsMiddleware;

	protected string $name = '';

	/**
	 * @psalm-param class-string $controller
	 */
	public function __construct(
		protected readonly RouteAdder $adder,
		protected readonly array|string $path,
		protected readonly string $controller,
		protected readonly string|array $args,
	) {
		if (!class_exists($controller)) {
			throw new RuntimeException("Endpoint controller {$controller} does not exist!");
		}
	}

	public function add(): void
	{
		if (is_array($this->args)) {
			$args = '/' . implode('/', array_map(fn($arg) => '{' . (string) $arg . '}', $this->args));
		} else {
			$args = '/{' . $this->args . '}';
		}

		if (is_array($this->path)) {
			assert(is_string($this->path[0]));
			assert(is_string($this->path[1]));
			$plural = $this->path[0];
			$singular = $this->path[1] . $args;
		} else {
			$plural = $this->path;
			$singular = $this->path . $args;
		}

		$this->addRoutes($plural, $singular);
	}

	public function name(string $name): static
	{
		$this->name = $name;

		return $this;
	}

	protected function addRoutes(
		string $plural,
		string $singular,
	): void {
		$this->addRoute('DELETE', $plural, 'deleteList');
		$this->addRoute('DELETE', $singular, 'delete');
		$this->addRoute('GET', $plural, 'list');
		$this->addRoute('GET', $singular, 'get');
		$this->addRoute('HEAD', $plural, 'headList');
		$this->addRoute('HEAD', $singular, 'head');
		$this->addRoute('OPTIONS', $plural, 'optionsList');
		$this->addRoute('OPTIONS', $singular, 'options');
		$this->addRoute('PATCH', $singular, 'patch');
		$this->addRoute('POST', $plural, 'post');
		$this->addRoute('PUT', $singular, 'put');
	}

	protected function addRoute(string $httpMethod, string $path, string $controllerMethod): void
	{
		if (method_exists($this->controller, $controllerMethod)) {
			$name = $this->name ? $this->name . '-' . $controllerMethod : '';

			$this->adder->addRoute(
				(new Route($path, [$this->controller, $controllerMethod], $name))
					->method($httpMethod)
					->middleware(...$this->middleware),
			);
		}
	}
}