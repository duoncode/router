<?php

declare(strict_types=1);

namespace Celemas\Router\Tests\Fixtures;

use Celemas\Router\Route;

class TestControllerWithRoute
{
	public function __construct(
		protected Route $route,
	) {}

	public function routeOnly(): string
	{
		return $this->route::class;
	}
}
