<?php

declare(strict_types=1);

namespace Duon\Router\Tests;

use Duon\Router\Exception\MethodNotAllowedException;
use Duon\Router\Exception\RuntimeException;
use Duon\Router\Exception\ValueError;
use Duon\Router\Group;
use Duon\Router\Route;
use Duon\Router\Router;
use Duon\Router\Tests\Fixtures\TestController;
use Duon\Router\Tests\Fixtures\TestEndpoint;
use Duon\Router\Tests\Fixtures\TestMiddleware1;
use Duon\Router\Tests\Fixtures\TestMiddleware2;
use Duon\Router\Tests\Fixtures\TestMiddleware3;

class GroupTest extends TestCase
{
	public function testMatchingNamed(): void
	{
		$router = new Router();
		$index = new Route('/', fn() => null, 'index');
		$router->addRoute($index);

		$group = new Group('/albums', function (Group $group) {
			$ctrl = TestController::class;

			$group->addRoute(Route::get('/home', "{$ctrl}::albumHome", 'home'));
			$group->addRoute(Route::get('/{name}', "{$ctrl}::albumName", 'name'));
			$group->addRoute(Route::get('', "{$ctrl}::albumList", 'list'));
		}, 'albums:');
		$group->create($router);

		$this->assertSame('index', $router->match($this->request(method: 'GET', uri: ''))->name());
		$this->assertSame('albums:name', $router->match($this->request(method: 'GET', uri: '/albums/symbolic'))->name());
		$this->assertSame('albums:home', $router->match($this->request(method: 'GET', uri: '/albums/home'))->name());
		$this->assertSame('albums:list', $router->match($this->request(method: 'GET', uri: '/albums'))->name());
		$this->assertSame('/albums/symbolic', $router->routeUrl('albums:name', name: 'symbolic'));
	}

	public function testMatchingUnnamed(): void
	{
		$router = new Router();
		$index = new Route('/', fn() => null);
		$router->addRoute($index);

		$group = new Group('/albums', function (Group $group) {
			$ctrl = TestController::class;

			$group->addRoute(Route::get('/home', "{$ctrl}::albumHome"));
			$group->addRoute(Route::get('/{name}', "{$ctrl}::albumName"));
			$group->addRoute(Route::get('', "{$ctrl}::albumList"));
		});
		$group->create($router);

		$this->assertSame('', $router->match($this->request('GET', ''))->name());
		$this->assertSame('', $router->match($this->request('GET', '/albums/symbolic'))->name());
		$this->assertSame('', $router->match($this->request('GET', '/albums/home'))->name());
		$this->assertSame('', $router->match($this->request('GET', '/albums'))->name());
	}

	public function testMatchingWithHelperMethods(): void
	{
		$this->throws(MethodNotAllowedException::class);

		$router = new Router();
		$index = new Route('/', fn() => null);
		$router->addRoute($index);

		$group = new Group('/helper', function (Group $group) {
			$ctrl = TestController::class;

			$group->get('/get', "{$ctrl}::albumHome", 'getroute');
			$group->post('/post', "{$ctrl}::albumHome", 'postroute');
			$group->put('/put', "{$ctrl}::albumHome", 'putroute');
			$group->patch('/patch', "{$ctrl}::albumHome", 'patchroute');
			$group->delete('/delete', "{$ctrl}::albumHome", 'deleteroute');
			$group->options('/options', "{$ctrl}::albumHome", 'optionsroute');
			$group->head('/head', "{$ctrl}::albumHome", 'headroute');
			$group->route('/route', "{$ctrl}::albumHome", 'allroute');
		}, 'helper:');
		$group->create($router);

		$this->assertSame('helper:getroute', $router->match($this->request('GET', '/helper/get'))->name());
		$this->assertSame('helper:postroute', $router->match($this->request('POST', '/helper/post'))->name());
		$this->assertSame('helper:putroute', $router->match($this->request('PUT', '/helper/put'))->name());
		$this->assertSame('helper:patchroute', $router->match($this->request('PATCH', '/helper/patch'))->name());
		$this->assertSame('helper:deleteroute', $router->match($this->request('DELETE', '/helper/delete'))->name());
		$this->assertSame('helper:optionsroute', $router->match($this->request('OPTIONS', '/helper/options'))->name());
		$this->assertSame('helper:headroute', $router->match($this->request('HEAD', '/helper/head'))->name());
		$this->assertSame('helper:allroute', $router->match($this->request('GET', '/helper/route'))->name());
		$this->assertSame('helper:allroute', $router->match($this->request('HEAD', '/helper/route'))->name());

		// raises not allowed
		$router->match($this->request('GET', '/helper/delete'));
	}

	public function testControllerPrefixing(): void
	{
		$router = new Router();
		$index = new Route('/', fn() => null);
		$router->addRoute($index);

		$group = (new Group('/albums', function (Group $group) {
			$group->addRoute(Route::get('-list', 'albumList', 'list'));
		}, 'albums-'))->controller(TestController::class);
		$group->create($router);

		$route = $router->match($this->request(method: 'GET', uri: '/albums-list'));
		$this->assertSame('albums-list', $route->name());
		$this->assertSame([TestController::class, 'albumList'], $route->view());
	}

	public function testEndpointInGroup(): void
	{
		$router = new Router();
		$index = new Route('/', fn() => null);
		$router->addRoute($index);

		(new Group('/media', function (Group $group) {
			$group->endpoint('/albums', TestEndpoint::class, 'id')->name('albums')->add();
		}, 'media-'))->create($router);

		$route = $router->match($this->request(method: 'GET', uri: '/media/albums/666'));
		$this->assertSame('media-albums-get', $route->name());
		$this->assertSame([TestEndpoint::class, 'get'], $route->view());
		$this->assertSame(['id' => '666'], $route->args());
	}

	public function testNestedGroups(): void
	{
		$router = new Router();
		$mw1 = new TestMiddleware1();
		$mw2 = new TestMiddleware2();
		$mw3 = new TestMiddleware3();

		(new Group('/media', function (Group $group) use ($mw1, $mw2, $mw3) {
			// Create using ::group - will not be created immediately
			$group->group('/music', function (Group $group) use ($mw1, $mw2, $mw3) {
				// Create using ::addGroup - will internally be created immediately
				$group->addGroup((new Group('/albums', function (Group $group) use ($mw1, $mw3) {
					// Create using ::group shortcut and create immediately
					$group->group('/songs', function (Group $group) use ($mw1) {
						// Create  in place - checks if it skips already created groups
						$group->endpoint('/times', TestEndpoint::class, 'id')
							->name('times')
							->middleware($mw1)
							->add();
					}, 'songs-')->middleware($mw3)->create($group);
				}, 'albums-'))->middleware($mw2));
			}, 'music-');
		}, 'media-'))->middleware($mw1)->create($router);

		$route = $router->match($this->request(method: 'GET', uri: '/media/music/albums/songs/times/666'));
		$this->assertSame('media-music-albums-songs-times-get', $route->name());
		$this->assertSame([TestEndpoint::class, 'get'], $route->view());
		$this->assertSame('/media/music/albums/songs/times/{id}', $route->pattern());
		$this->assertSame(['id' => '666'], $route->args());
		$this->assertSame([$mw1, $mw2, $mw3, $mw1], $route->getMiddleware());
	}

	public function testControllerPrefixingErrorUsingClosure(): void
	{
		$this->throws(ValueError::class, 'Cannot add controller');

		$router = new Router();

		$group = (new Group('/albums', function (Group $group) {
			$group->addRoute(
				Route::get('-list', function () {}),
			);
		}))->controller(TestController::class);
		$group->create($router);
	}

	public function testControllerPrefixingErrorUsingEndpoint(): void
	{
		$this->throws(ValueError::class, 'Cannot add controller');

		$router = new Router();

		$group = (new Group('/media', function (Group $group) {
			$group->endpoint('/albums', TestEndpoint::class, 'id')->name('albums')->add();
		}))->controller(TestController::class);
		$group->create($router);
	}

	public function testMiddleware(): void
	{
		$router = new Router();

		$group = (new Group('/albums', function (Group $group) {
			$ctrl = TestController::class;

			$group->addRoute(Route::get('', "{$ctrl}::albumList"));
			$group->addRoute(Route::get('/home', "{$ctrl}::albumHome")->middleware(new TestMiddleware3()));
			$group->addRoute(Route::get('/{name}', "{$ctrl}::albumName"));
		}))->middleware(new TestMiddleware2());
		$group->create($router);

		$route = $router->match($this->request(method: 'GET', uri: '/albums/human'));
		$middleware = $route->getMiddleware();
		$this->assertSame(1, count($middleware));
		$this->assertInstanceof(TestMiddleware2::class, $middleware[0]);

		$route = $router->match($this->request(method: 'GET', uri: '/albums/home'));
		$middleware = $route->getMiddleware();
		$this->assertSame(2, count($middleware));
		$this->assertInstanceof(TestMiddleware2::class, $middleware[0]);
		$this->assertInstanceof(TestMiddleware3::class, $middleware[1]);
	}

	public function testFailWithoutCallingCreateBefore(): void
	{
		$this->throws(RuntimeException::class, 'RouteAdder not set');

		$group = new Group('/albums', function (Group $group) {}, 'test:');
		$group->addRoute(Route::get('/', fn() => ''));
	}
}