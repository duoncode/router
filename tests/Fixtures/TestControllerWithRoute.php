<?php

declare(strict_types=1);

namespace Duon\Router\Tests\Fixtures;

use Duon\Router\Route;

class TestControllerWithRoute
{
	public function __construct(protected Route $route) {}

	public function routeOnly(): string
	{
		return $this->route::class;
	}
}