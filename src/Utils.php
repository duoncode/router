<?php

declare(strict_types=1);

namespace Duon\Router;

use Closure;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionObject;

function getReflectionFunction(
	callable $callable,
): ReflectionFunction|ReflectionMethod {
	if ($callable instanceof Closure) {
		return new ReflectionFunction($callable);
	}

	if (is_object($callable)) {
		return (new ReflectionObject($callable))->getMethod('__invoke');
	}

	/** @var Closure|non-falsy-string $callable */
	return new ReflectionFunction($callable);
}