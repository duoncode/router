<?php

declare(strict_types=1);

namespace Duon\Router;

use Duon\Wire\Call;
use Duon\Wire\CallableResolver;
use Duon\Wire\Creator;
use Psr\Container\ContainerInterface as Container;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionFunctionAbstract;
use ReflectionObject;

class AttributesResolver
{
	protected readonly array $attributes;

	/** @param list<ReflectionClass|ReflectionFunctionAbstract> $reflectors */
	public function __construct(
		array $reflectors,
		protected readonly ?Container $container,
	) {
		$reflectionAttributes = array_merge(
			...array_map(fn($reflector) => $reflector->getAttributes(), $reflectors),
		);

		$this->attributes = array_map(
			function ($attribute) {
				return $this->newAttributeInstance($attribute);
			},
			$reflectionAttributes,
		);
	}

	/** @param ?class-string $filter */
	public function get(?string $filter = null): array
	{
		if ($filter) {
			return array_values(
				array_filter($this->attributes, function ($attribute) use ($filter) {
					return $attribute instanceof $filter;
				}),
			);
		}

		return $this->attributes;
	}

	protected function newAttributeInstance(ReflectionAttribute $attribute): object
	{
		$instance = $attribute->newInstance();
		$callAttrs = (new ReflectionObject($instance))->getAttributes(Call::class);

		if (count($callAttrs) > 0) {
			$resolver = new CallableResolver(new Creator($this->container));

			// See if the attribute itself has one or more Call attributes. If so,
			// resolve/autowire the arguments of the method it states and call it.
			foreach ($callAttrs as $callAttr) {
				$callAttr = $callAttr->newInstance();
				$methodToResolve = $callAttr->method;

				/** @psalm-var callable */
				$callable = [$instance, $methodToResolve];
				$args = $resolver->resolve($callable, $callAttr->args);
				$callable(...$args);
			}
		}

		return $instance;
	}
}