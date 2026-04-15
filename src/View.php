<?php

declare(strict_types=1);

namespace Duon\Router;

use Closure;
use Duon\Router\Exception\RuntimeException;
use Duon\Wire\Creator;
use Duon\Wire\Exception\WireException;
use Psr\Container\ContainerInterface as Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use ReflectionClass;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

final class View
{
	use AddsBeforeAfter;

	protected Creator $creator;
	protected ?AttributesResolver $attributes = null;

	/** @var Closure|array{class-string, string} */
	protected Closure|array $view;

	/**
	 * @param list<Before> $beforeHandlers
	 * @param list<After> $afterHandlers
	 */
	public function __construct(
		protected readonly Route $route,
		protected readonly ?Container $container,
		array $beforeHandlers = [],
		array $afterHandlers = [],
	) {
		$this->creator = new Creator($container);
		$this->view = $this->prepareView($route->view());
		$this->setBeforeHandlers($this->doMergeBeforeHandlers($beforeHandlers, $route->beforeHandlers()));
		$this->setAfterHandlers($this->doMergeAfterHandlers($afterHandlers, $route->afterHandlers()));
	}

	public function execute(Request $request): Response
	{
		$closure = $this->getClosure($request);

		/** @var list<Before> $beforeAttributes */
		$beforeAttributes = $this->attributes(Before::class);
		foreach ($this->mergeBeforeHandlers($beforeAttributes) as $handler) {
			$request = $handler->handle($request);
		}

		/** @var mixed $result */
		$result = ($closure)(...$this->getArgs(getReflectionFunction($closure), $request));

		/** @var list<After> $afterAttributes */
		$afterAttributes = $this->attributes(After::class);
		foreach ($this->mergeAfterHandlers($afterAttributes) as $handler) {
			/** @var mixed $result */
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
	public function attributes(?string $filter = null): array
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

	/** @return Closure|array{class-string, string} */
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
			/** @var class-string $controllerName */
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
		$viewString = $controllerName . '::' . $method;

		throw new RuntimeException("View method not found {$viewString}");
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
	 * - If parameter resolution fails and a default value exists, the default
	 *   will be used.
	 * - Exceptions thrown while constructing dependencies bubble unchanged.
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
					'int' => is_numeric($routeArgs[$name])
						? (int) $routeArgs[$name]
						: throw new RuntimeException($errMsg . "Cannot cast '{$name}' to int"),
					'float' => is_numeric($routeArgs[$name])
						? (float) $routeArgs[$name]
						: throw new RuntimeException($errMsg . "Cannot cast '{$name}' to float"),
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
		} catch (RuntimeException|WireException $e) {
			if ($param->isDefaultValueAvailable()) {
				return $param->getDefaultValue();
			}

			$code = $e->getCode();

			throw new RuntimeException($errMsg . $e->getMessage(), is_int($code) ? $code : 0, $e);
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

			if (!class_exists($typeName) && !interface_exists($typeName)) {
				throw new RuntimeException(
					"Type '{$typeName}' is not a class or interface. Source: \n"
						. $this->paramInfo($param),
				);
			}

			return $this->creator->create($typeName, predefinedTypes: [Request::class => $request]);
		}
		if ($type) {
			throw new RuntimeException(
				"Autowiring does not support union or intersection types. Source: \n"
					. $this->paramInfo($param),
			);
		}

		throw new RuntimeException(
			"Autowired entities need to have typed constructor parameters. Source: \n"
				. $this->paramInfo($param),
		);

	}

	public function paramInfo(ReflectionParameter $param): string
	{
		$type = $param->getType();
		$rf = $param->getDeclaringFunction();
		$rc = null;

		if ($rf instanceof ReflectionMethod) {
			$rc = $rf->getDeclaringClass();
		}

		return ($rc ? $rc->getName() . '::' : '')
			. ($rf->getName() . '(..., ')
			. ($type ? (string) $type . ' ' : '')
			. '$' . $param->getName() . ', ...)';
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
