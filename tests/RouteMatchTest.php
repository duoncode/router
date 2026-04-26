<?php

declare(strict_types=1);

namespace Duon\Router\Tests;

use Duon\Router\Route;
use Duon\Router\RouteMatch;

class RouteMatchTest extends TestCase
{
	public function testAccessors(): void
	{
		$route = Route::get('/albums/{id}', static fn() => null, 'album');
		$match = new RouteMatch($route, ['id' => '13'], 'get');

		$this->assertSame($route, $match->route());
		$this->assertSame(['id' => '13'], $match->params());
		$this->assertSame('GET', $match->method());
	}

	public function testReturnedParamsDoNotMutateMatch(): void
	{
		$route = Route::get('/albums/{id}', static fn() => null, 'album');
		$match = new RouteMatch($route, ['id' => '13'], 'GET');
		$params = $match->params();
		$params['id'] = '42';

		$this->assertSame(['id' => '13'], $match->params());
	}
}
