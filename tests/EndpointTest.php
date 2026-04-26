<?php

declare(strict_types=1);

namespace Duon\Router\Tests;

use Duon\Router\Endpoint;
use Duon\Router\Exception\RuntimeException;
use Duon\Router\Router;
use Duon\Router\Tests\Fixtures\TestEndpoint;

class EndpointTest extends TestCase
{
	public function testEndpointWithDefaults(): void
	{
		$router = new Router();
		new Endpoint($router, '/endpoints', TestEndpoint::class, 'id')->add();

		$match = $router->match($this->request('DELETE', '/endpoints'));
		$route = $match->route();
		$this->assertSame('/endpoints', $route->pattern());
		$this->assertSame([TestEndpoint::class, 'deleteList'], $route->view());
		$this->assertSame([], $match->params());

		$match = $router->match($this->request('DELETE', '/endpoints/13'));
		$route = $match->route();
		$this->assertSame('/endpoints/{id}', $route->pattern());
		$this->assertSame([TestEndpoint::class, 'delete'], $route->view());
		$this->assertSame(['id' => '13'], $match->params());

		$match = $router->match($this->request('GET', '/endpoints'));
		$route = $match->route();
		$this->assertSame('/endpoints', $route->pattern());
		$this->assertSame([TestEndpoint::class, 'list'], $route->view());
		$this->assertSame([], $match->params());

		$match = $router->match($this->request('GET', '/endpoints/13'));
		$route = $match->route();
		$this->assertSame('/endpoints/{id}', $route->pattern());
		$this->assertSame([TestEndpoint::class, 'get'], $route->view());
		$this->assertSame(['id' => '13'], $match->params());

		$match = $router->match($this->request('HEAD', '/endpoints'));
		$route = $match->route();
		$this->assertSame('/endpoints', $route->pattern());
		$this->assertSame([TestEndpoint::class, 'headList'], $route->view());
		$this->assertSame([], $match->params());

		$match = $router->match($this->request('HEAD', '/endpoints/13'));
		$route = $match->route();
		$this->assertSame('/endpoints/{id}', $route->pattern());
		$this->assertSame([TestEndpoint::class, 'head'], $route->view());
		$this->assertSame(['id' => '13'], $match->params());

		$match = $router->match($this->request('OPTIONS', '/endpoints'));
		$route = $match->route();
		$this->assertSame('/endpoints', $route->pattern());
		$this->assertSame([TestEndpoint::class, 'optionsList'], $route->view());
		$this->assertSame([], $match->params());

		$match = $router->match($this->request('OPTIONS', '/endpoints/13'));
		$route = $match->route();
		$this->assertSame('/endpoints/{id}', $route->pattern());
		$this->assertSame([TestEndpoint::class, 'options'], $route->view());
		$this->assertSame(['id' => '13'], $match->params());

		$match = $router->match($this->request('PATCH', '/endpoints/13'));
		$route = $match->route();
		$this->assertSame('/endpoints/{id}', $route->pattern());
		$this->assertSame([TestEndpoint::class, 'patch'], $route->view());
		$this->assertSame(['id' => '13'], $match->params());

		$match = $router->match($this->request('POST', '/endpoints'));
		$route = $match->route();
		$this->assertSame('/endpoints', $route->pattern());
		$this->assertSame([TestEndpoint::class, 'post'], $route->view());
		$this->assertSame([], $match->params());

		$match = $router->match($this->request('PUT', '/endpoints/13'));
		$route = $match->route();
		$this->assertSame('/endpoints/{id}', $route->pattern());
		$this->assertSame([TestEndpoint::class, 'put'], $route->view());
		$this->assertSame(['id' => '13'], $match->params());
	}

	public function testEndpointWithPluralSingular(): void
	{
		$router = new Router();
		new Endpoint($router, ['/endpoints', '/endpoint'], TestEndpoint::class, 'id')->add();

		$match = $router->match($this->request('DELETE', '/endpoints'));
		$route = $match->route();
		$this->assertSame('/endpoints', $route->pattern());
		$this->assertSame([TestEndpoint::class, 'deleteList'], $route->view());
		$this->assertSame([], $match->params());

		$match = $router->match($this->request('DELETE', '/endpoint/13'));
		$route = $match->route();
		$this->assertSame('/endpoint/{id}', $route->pattern());
		$this->assertSame([TestEndpoint::class, 'delete'], $route->view());
		$this->assertSame(['id' => '13'], $match->params());

		$match = $router->match($this->request('GET', '/endpoints'));
		$route = $match->route();
		$this->assertSame('/endpoints', $route->pattern());
		$this->assertSame([TestEndpoint::class, 'list'], $route->view());
		$this->assertSame([], $match->params());

		$match = $router->match($this->request('GET', '/endpoint/13'));
		$route = $match->route();
		$this->assertSame('/endpoint/{id}', $route->pattern());
		$this->assertSame([TestEndpoint::class, 'get'], $route->view());
		$this->assertSame(['id' => '13'], $match->params());

		$match = $router->match($this->request('HEAD', '/endpoints'));
		$route = $match->route();
		$this->assertSame('/endpoints', $route->pattern());
		$this->assertSame([TestEndpoint::class, 'headList'], $route->view());
		$this->assertSame([], $match->params());

		$match = $router->match($this->request('HEAD', '/endpoint/13'));
		$route = $match->route();
		$this->assertSame('/endpoint/{id}', $route->pattern());
		$this->assertSame([TestEndpoint::class, 'head'], $route->view());
		$this->assertSame(['id' => '13'], $match->params());

		$match = $router->match($this->request('OPTIONS', '/endpoints'));
		$route = $match->route();
		$this->assertSame('/endpoints', $route->pattern());
		$this->assertSame([TestEndpoint::class, 'optionsList'], $route->view());
		$this->assertSame([], $match->params());

		$match = $router->match($this->request('OPTIONS', '/endpoint/13'));
		$route = $match->route();
		$this->assertSame('/endpoint/{id}', $route->pattern());
		$this->assertSame([TestEndpoint::class, 'options'], $route->view());
		$this->assertSame(['id' => '13'], $match->params());

		$match = $router->match($this->request('PATCH', '/endpoint/13'));
		$route = $match->route();
		$this->assertSame('/endpoint/{id}', $route->pattern());
		$this->assertSame([TestEndpoint::class, 'patch'], $route->view());
		$this->assertSame(['id' => '13'], $match->params());

		$match = $router->match($this->request('POST', '/endpoints'));
		$route = $match->route();
		$this->assertSame('/endpoints', $route->pattern());
		$this->assertSame([TestEndpoint::class, 'post'], $route->view());
		$this->assertSame([], $match->params());

		$match = $router->match($this->request('PUT', '/endpoint/13'));
		$route = $match->route();
		$this->assertSame('/endpoint/{id}', $route->pattern());
		$this->assertSame([TestEndpoint::class, 'put'], $route->view());
		$this->assertSame(['id' => '13'], $match->params());
	}

	public function testEndpointWithName(): void
	{
		$router = new Router();
		new Endpoint($router, '/endpoints', TestEndpoint::class, 'id')->name('albums')->add();

		$match = $router->match($this->request('DELETE', '/endpoints'));
		$route = $match->route();
		$this->assertSame('albums-deleteList', $route->name());

		$match = $router->match($this->request('DELETE', '/endpoints/13'));
		$route = $match->route();
		$this->assertSame('albums-delete', $route->name());

		$match = $router->match($this->request('GET', '/endpoints'));
		$route = $match->route();
		$this->assertSame('albums-list', $route->name());

		$match = $router->match($this->request('GET', '/endpoints/13'));
		$route = $match->route();
		$this->assertSame('albums-get', $route->name());

		$match = $router->match($this->request('HEAD', '/endpoints'));
		$route = $match->route();
		$this->assertSame('albums-headList', $route->name());

		$match = $router->match($this->request('HEAD', '/endpoints/13'));
		$route = $match->route();
		$this->assertSame('albums-head', $route->name());

		$match = $router->match($this->request('OPTIONS', '/endpoints'));
		$route = $match->route();
		$this->assertSame('albums-optionsList', $route->name());

		$match = $router->match($this->request('OPTIONS', '/endpoints/13'));
		$route = $match->route();
		$this->assertSame('albums-options', $route->name());

		$match = $router->match($this->request('PATCH', '/endpoints/13'));
		$route = $match->route();
		$this->assertSame('albums-patch', $route->name());

		$match = $router->match($this->request('POST', '/endpoints'));
		$route = $match->route();
		$this->assertSame('albums-post', $route->name());

		$match = $router->match($this->request('PUT', '/endpoints/13'));
		$route = $match->route();
		$this->assertSame('albums-put', $route->name());
	}

	public function testEndpointWithMultipleArgs(): void
	{
		$router = new Router();
		new Endpoint($router, '/endpoints', TestEndpoint::class, ['id', 'category'])->add();

		$match = $router->match($this->request('POST', '/endpoints'));
		$route = $match->route();
		$this->assertSame('/endpoints', $route->pattern());
		$this->assertSame([TestEndpoint::class, 'post'], $route->view());
		$this->assertSame([], $match->params());

		$match = $router->match($this->request('PUT', '/endpoints/13/albums'));
		$route = $match->route();
		$this->assertSame('/endpoints/{id}/{category}', $route->pattern());
		$this->assertSame([TestEndpoint::class, 'put'], $route->view());
		$this->assertSame(['id' => '13', 'category' => 'albums'], $match->params());
	}

	public function testEndpointWithNonexistentController(): void
	{
		$this->throws(RuntimeException::class, 'does not exist');

		$router = new Router();
		new Endpoint($router, '/endpoints', 'NonexistentController', 'id');
	}
}
