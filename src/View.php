<?php

declare(strict_types=1);

namespace Duon\Router;

use Closure;
use Duon\Router\Exception\RuntimeException;
use Duon\Wire\Creator;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface as Container;
use Psr\Container\NotFoundExceptionInterface as NotFoundException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use ReflectionClass;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Throwable;

class View
{
	use AddsBeforeAfter;

	protected Creator $creator;
	protected ?AttributesResolver $attributes = null;
	protected ?Closure $closure = null;
	protected Closure|array $view;

	public function __construct(
		protected readonly Route $route,
		protected readonly ?Container $container,
		/** @param list<Before> */
		array $beforeHandlers = [],
		/** @param list<After> */
		array $afterHandlers = [],
	) {
		$this->creator = new Creator($container);
		$this->view = $this->prepareView($route->view());
		$this->setBeforeHandlers($this->mergeHandlers($beforeHandlers, $route->beforeHandlers()));
		$this->setAfterHandlers($this->mergeHandlers($afterHandlers, $route->afterHandlers()));
	}

	public function execute(Request $request): Response
	{
		$closure = $this->getClosure($request);

		foreach ($this->mergeBeforeHandlers($this->attributes(Before::class)) as $handler) {
			$request = $handler->handle($request);
		}

		$result = ($closure)(...$this->getArgs(getReflectionFunction($closure), $request));

		foreach ($this->mergeAfterHandlers($this->attributes(After::class)) as $handler) {
			$result = $handler->handle($result);
		}

		if ($result instanceof Response) {
			return $result;
		}

		if ($result instanceof ResponseWrapper) {
			return $result->unwrap();
		}

		throw new RuntimeException('Unable to determine a response handler for the returned value of the view');
	}

	/** @param ?class-string $filter*/
	public function attributes(string $filter = null): array
	{
		if (!isset($this->attributes)) {
			if (is_callable($this->view)) {
				$this->attributes = new AttributesResolver([getReflectionFunction($this->view)], $this->container);
			} else {
				[$controller, $method] = $this->view;
				$reflectionClass = new ReflectionClass($controller);
				$this->attributes = new AttributesResolver([
					$reflectionClass,
					$reflectionClass->getMethod($method),
				], $this->container);
			}
		}

		return $this->attributes->get($filter);
	}

	protected function prepareView(callable|string|array $view): Closure|array
	{
		if (is_callable($view)) {
			/** @var callable $view -- Psalm complains even though we use is_callable() */
			return Closure::fromCallable($view);
		}

		if (is_array($view)) {
			[$controllerName, $method] = $view;
			assert(is_string($controllerName));
			assert(is_string($method));
		} else {
			if (!str_contains($view, '::')) {
				$view .= '::__invoke';
			}

			[$controllerName, $method] = explode('::', $view);
		}

		if (class_exists($controllerName)) {
			return [$controllerName, $method];
		}

		throw new RuntimeException("Controller not found {$controllerName}");
	}

	protected function getClosure(Request $request): Closure
	{
		if ($this->view instanceof Closure) {
			return $this->view;
		}

		[$controllerName, $method] = $this->view;
		$rc = new ReflectionClass($controllerName);
		$constructor = $rc->getConstructor();
		$args = $constructor ? $this->getArgs($constructor, $request) : [];
		$controller = $rc->newInstance(...$args);

		if (method_exists($controller, $method)) {
			return Closure::fromCallable([$controller, $method]);
		}
		$view = $controllerName . '::' . $method;

		throw new RuntimeException("View method not found {$view}");
	}

	/**
	 * Determines the arguments passed to the view and/or controller constructor.
	 *
	 * - If a view parameter implements Request, the request will be passed.
	 * - If a view parameter is a subclass of Route, the route will be passed.
	 * - If names of the view parameters match names of the route arguments
	 *   it will try to convert the argument to the parameter type and add it to
	 *   the returned args list.
	 * - If the parameter is typed, try to resolve it via container or
	 *   autowiring.
	 * - If a parameter has a default value, it will be used.
	 * - Otherwise fail.
	 *
	 * @psalm-suppress MixedAssignment -- $args values are mixed
	 */
	protected function getArgs(ReflectionFunctionAbstract $rf, Request $request): array
	{
		/** @var array<string, mixed> */
		$args = [];
		$params = $rf->getParameters();
		$errMsg = 'View parameters cannot be resolved. Details: ';

		foreach ($params as $param) {
			$name = $param->getName();
			$routeArgs = $this->route->args();

			if (array_key_exists($name, $routeArgs)) {
				$args[$name] = match ((string) $param->getType()) {
					'int' => is_numeric($routeArgs[$name]) ?
						(int) $routeArgs[$name] :
						throw new RuntimeException($errMsg . "Cannot cast '{$name}' to int"),
					'float' => is_numeric($routeArgs[$name]) ?
						(float) $routeArgs[$name] :
						throw new RuntimeException($errMsg . "Cannot cast '{$name}' to float"),
					'string' => $routeArgs[$name],
					default => $this->resolveUnknown($param, $request, $errMsg),
				};
			} else {
				$args[$name] = $this->resolveUnknown($param, $request, $errMsg);
			}
		}

		assert(count($params) === count($args));

		return $args;
	}

	protected function resolveUnknown(ReflectionParameter $param, Request $request, string $errMsg): mixed
	{
		try {
			return $this->resolveParam($param, $request);
		} catch (Throwable $e) {
			// Check if the view parameter has a default value
			if ($param->isDefaultValueAvailable()) {
				return $param->getDefaultValue();
			}

			throw new RuntimeException($errMsg . $e->getMessage(), $e->getCode(), $e);
		}
	}

	protected function resolveParam(ReflectionParameter $param, Request $request): mixed
	{
		$type = $param->getType();

		if ($type instanceof ReflectionNamedType) {
			$typeName = ltrim($type->getName(), '?');

			if ($typeName === Request::class || is_subclass_of($typeName, Request::class)) {
				return $request;
			}

			if ($typeName === Route::class || is_subclass_of($typeName, Route::class)) {
				return $this->route;
			}

			try {
				return $this->creator->create($typeName, predefinedTypes: [Request::class => $request]);
			} catch (NotFoundException | ContainerException  $e) {
				if ($param->isDefaultValueAvailable()) {
					return $param->getDefaultValue();
				}

				throw $e;
			}
		} else {
			if ($type) {
				throw new ContainerException(
					"Autowiring does not support union or intersection types. Source: \n" .
						$this->paramInfo($param),
				);
			}

			throw new ContainerException(
				"Autowired entities need to have typed constructor parameters. Source: \n" .
					$this->paramInfo($param),
			);
		}
	}

	public function paramInfo(ReflectionParameter $param): string
	{
		$type = $param->getType();
		$rf = $param->getDeclaringFunction();
		$rc = null;

		if ($rf instanceof ReflectionMethod) {
			$rc = $rf->getDeclaringClass();
		}

		return ($rc ? $rc->getName() . '::' : '') .
			($rf->getName() . '(..., ') .
			($type ? (string) $type . ' ' : '') .
			'$' . $param->getName() . ', ...)';
	}

	public function middleware(): array
	{
		$middlewareAttributes = $this->attributes(Middleware::class);

		return array_merge(
			$this->route->getMiddleware(),
			$middlewareAttributes,
		);
	}
}