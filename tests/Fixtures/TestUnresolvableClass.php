<?php

declare(strict_types=1);

namespace Celemas\Router\Tests\Fixtures;

/**
 * A class that cannot be autowired because it has required constructor params
 * that aren't resolvable without a container.
 */
class TestUnresolvableClass
{
	public function __construct(
		public readonly string $requiredParam,
	) {}
}
