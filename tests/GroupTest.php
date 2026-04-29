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
use Duon\Router\Tests\Fixtures\TestMiddleware1;
use Duon\Router\Tests\Fixtures\TestMiddleware2;
use Duon\Router\Tests\Fixtures\TestMiddleware3;

class GroupTest extends TestCase
{
	public function testMatchingNamed(): void
	{
		$router = new Router();
		$index = new Route('/', static fn() => null, 'index');
		$router->addRoute($index);

		$router->group(
			'/albums',
			static function (Group $group): void {
				$ctrl = TestController::class;

				$group->get('/home', "{$ctrl}::albumHome", 'home');
				$group->get('/{name}', "{$ctrl}::albumName", 'name');
				$group->get('', "{$ctrl}::albumList", 'list');
			},
			'albums:',
		);

		$this->assertSame(
			'index',
			$router->match($this->request(method: 'GET', uri: ''))->route()->name(),
		);
		$this->assertSame(
			'albums:name',
			$router->match($this->request(method: 'GET', uri: '/albums/symbolic'))->route()->name(),
		);
		$this->assertSame(
			'albums:home',
			$router->match($this->request(method: 'GET', uri: '/albums/home'))->route()->name(),
		);
		$this->assertSame(
			'albums:list',
			$router->match($this->request(method: 'GET', uri: '/albums'))->route()->name(),
		);
		$this->assertSame('/albums/symbolic', $router->url('albums:name', ['name' => 'symbolic']));
	}

	public function testMatchingUnnamed(): void
	{
		$router = new Router();
		$index = new Route('/', static fn() => null);
		$router->addRoute($index);

		$router->group('/albums', static function (Group $group): void {
			$ctrl = TestController::class;

			$group->get('/home', "{$ctrl}::albumHome");
			$group->get('/{name}', "{$ctrl}::albumName");
			$group->get('', "{$ctrl}::albumList");
		});

		$this->assertSame('', $router->match($this->request('GET', ''))->route()->name());
		$this->assertSame('', $router->match($this->request('GET', '/albums/symbolic'))->route()->name());
		$this->assertSame('', $router->match($this->request('GET', '/albums/home'))->route()->name());
		$this->assertSame('', $router->match($this->request('GET', '/albums'))->route()->name());
	}

	public function testMatchingWithHelperMethods(): void
	{
		$this->throws(MethodNotAllowedException::class);

		$router = new Router();
		$index = new Route('/', static fn() => null);
		$router->addRoute($index);

		$router->group(
			'/helper',
			static function (Group $group): void {
				$ctrl = TestController::class;

				$group->get('/get', "{$ctrl}::albumHome", 'getroute');
				$group->post('/post', "{$ctrl}::albumHome", 'postroute');
				$group->put('/put', "{$ctrl}::albumHome", 'putroute');
				$group->patch('/patch', "{$ctrl}::albumHome", 'patchroute');
				$group->delete('/delete', "{$ctrl}::albumHome", 'deleteroute');
				$group->options('/options', "{$ctrl}::albumHome", 'optionsroute');
				$group->head('/head', "{$ctrl}::albumHome", 'headroute');
				$group->any('/route', "{$ctrl}::albumHome", 'allroute');
			},
			'helper:',
		);

		$this->assertSame(
			'helper:getroute',
			$router->match($this->request('GET', '/helper/get'))->route()->name(),
		);
		$this->assertSame(
			'helper:postroute',
			$router->match($this->request('POST', '/helper/post'))->route()->name(),
		);
		$this->assertSame(
			'helper:putroute',
			$router->match($this->request('PUT', '/helper/put'))->route()->name(),
		);
		$this->assertSame(
			'helper:patchroute',
			$router->match($this->request('PATCH', '/helper/patch'))->route()->name(),
		);
		$this->assertSame(
			'helper:deleteroute',
			$router->match($this->request('DELETE', '/helper/delete'))->route()->name(),
		);
		$this->assertSame(
			'helper:optionsroute',
			$router->match($this->request('OPTIONS', '/helper/options'))->route()->name(),
		);
		$this->assertSame(
			'helper:headroute',
			$router->match($this->request('HEAD', '/helper/head'))->route()->name(),
		);
		$this->assertSame(
			'helper:allroute',
			$router->match($this->request('GET', '/helper/route'))->route()->name(),
		);
		$this->assertSame(
			'helper:allroute',
			$router->match($this->request('HEAD', '/helper/route'))->route()->name(),
		);

		$router->match($this->request('GET', '/helper/delete'))->route();
	}

	public function testControllerPrefixing(): void
	{
		$router = new Router();
		$index = new Route('/', static fn() => null);
		$router->addRoute($index);

		$router->group(
			'/albums',
			static function (Group $group): void {
				$group->get('-list', 'albumList', 'list');
				$group->controller(TestController::class);
			},
			'albums-',
		);

		$route = $router->match($this->request(method: 'GET', uri: '/albums-list'))->route();
		$this->assertSame('albums-list', $route->name());
		$this->assertSame([TestController::class, 'albumList'], $route->view());
	}

	public function testNestedGroups(): void
	{
		$router = new Router();
		$mw1 = new TestMiddleware1();
		$mw2 = new TestMiddleware2();
		$mw3 = new TestMiddleware3();

		$router->group(
			'/media',
			static function (Group $media) use ($mw1, $mw2, $mw3): void {
				$media->group(
					'/music',
					static function (Group $music) use ($mw1, $mw2, $mw3): void {
						$music->middleware($mw2);
						$music->group(
							'/albums',
							static function (Group $albums) use ($mw1, $mw3): void {
								$albums
									->group(
										'/songs',
										static function (Group $songs) use ($mw1): void {
											$songs
												->get('/times/{id}', [TestController::class, 'textView'], 'times')
												->middleware($mw1);
										},
										'songs-',
									)
									->middleware($mw3);
							},
							'albums-',
						);
					},
					'music-',
				);
				$media->middleware($mw1);
			},
			'media-',
		);

		$match = $router->match($this->request(
			method: 'GET',
			uri: '/media/music/albums/songs/times/666',
		));
		$route = $match->route();
		$this->assertSame('media-music-albums-songs-times', $route->name());
		$this->assertSame([TestController::class, 'textView'], $route->view());
		$this->assertSame('/media/music/albums/songs/times/{id}', $route->pattern());
		$this->assertSame(['id' => '666'], $match->params());
		$this->assertSame([$mw1, $mw2, $mw3, $mw1], $route->getMiddleware());
	}

	public function testNestedGroupCanBeAddedWhileParentFinalizes(): void
	{
		$router = new Router();

		$router->group(
			'/media',
			static function (Group $media): void {
				$media->group('/music', static function (Group $music) use ($media): void {
					$media->group(
						'/photos',
						static function (Group $photos): void {
							$photos->get('/{id}', [TestController::class, 'textView'], 'show');
						},
						'photos.',
					);
				});
			},
			'media.',
		);

		$this->assertSame('/media/photos/1', $router->url('media.photos.show', ['id' => 1]));
	}

	public function testControllerPrefixingErrorUsingClosure(): void
	{
		$this->throws(ValueError::class, 'Cannot add controller');

		$router = new Router();

		$router->group('/albums', static function (Group $group): void {
			$group->get('-list', static function (): void {});
			$group->controller(TestController::class);
		});
	}

	public function testControllerPrefixingErrorUsingArray(): void
	{
		$this->throws(ValueError::class, 'Cannot add controller');

		$router = new Router();

		$router->group('/media', static function (Group $group): void {
			$group->get('/albums', [TestController::class, 'textView']);
			$group->controller(TestController::class);
		});
	}

	public function testMiddlewareAppliesToRoutesDefinedBeforeIt(): void
	{
		$router = new Router();
		$mw2 = new TestMiddleware2();
		$mw3 = new TestMiddleware3();

		$router->group('/albums', static function (Group $group) use ($mw2, $mw3): void {
			$ctrl = TestController::class;

			$group->get('', "{$ctrl}::albumList");
			$group->get('/home', "{$ctrl}::albumHome")->middleware($mw3);
			$group->get('/{name}', "{$ctrl}::albumName");
			$group->middleware($mw2);
		});

		$route = $router->match($this->request(method: 'GET', uri: '/albums/human'))->route();
		$this->assertSame([$mw2], $route->getMiddleware());

		$route = $router->match($this->request(method: 'GET', uri: '/albums/home'))->route();
		$this->assertSame([$mw2, $mw3], $route->getMiddleware());
	}

	public function testCreateIsIdempotent(): void
	{
		$router = new Router();
		$group = $router->group(
			'/albums',
			static function (Group $group): void {
				$group->get('', [TestController::class, 'textView'], 'index');
			},
			'albums.',
		);

		$group->create($router);

		$this->assertSame('/albums', $router->url('albums.index'));
	}

	public function testFailWithoutCallingCreateBefore(): void
	{
		$this->throws(RuntimeException::class, 'RouteAdder not set');

		$group = new Group('/albums', static function (Group $group): void {}, 'test:');
		$group->addRoute(Route::get('/', static fn() => ''));
	}

	public function testFailNestedGroupWithoutCallingCreateBefore(): void
	{
		$this->throws(RuntimeException::class, 'RouteAdder not set');

		$group = new Group('/albums', static function (Group $group): void {}, 'test:');
		$group->group('/photos', static function (Group $group): void {});
	}
}
