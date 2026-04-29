<?php

declare(strict_types=1);

namespace Duon\Router\Tests;

use Duon\Router\Exception\InvalidArgumentException;
use Duon\Router\Exception\MethodNotAllowedException;
use Duon\Router\Exception\NotFoundException;
use Duon\Router\Exception\RuntimeException;
use Duon\Router\Group;
use Duon\Router\Route;
use Duon\Router\RouteMatch;
use Duon\Router\Router;
use Duon\Router\Tests\Fixtures\TestController;
use PHPUnit\Framework\Attributes\TestDox;

class RouterTest extends TestCase
{
	public function testMatching(): void
	{
		$router = new Router();
		$index = new Route('/', static fn() => null, 'index');
		$router->addRoute($index);
		$albums = new Route('/albums', static fn() => null);
		$router->addRoute($albums);
		$router->group('/albums', static function (Group $group): void {
			$ctrl = TestController::class;
			$group->get('/{name}', "{$ctrl}::albumName");
		});

		$match = $router->match($this->request('GET', ''));
		$this->assertInstanceOf(RouteMatch::class, $match);
		$this->assertSame('index', $match->route()->name());

		$this->assertSame($index, $router->match($this->request('GET', ''))->route());
		$this->assertSame($albums, $router->match($this->request('GET', '/albums'))->route());
		$this->assertSame($albums, $router->match($this->request('GET', '/albums?q=Symbolic'))->route());
		$this->assertSame('', $router->match($this->request('GET', '/albums/name'))->route()->name());
	}

	public function testPrefixMatching(): void
	{
		$router = new Router('/prefix');
		$index = new Route('/', static fn() => null, 'index');
		$router->addRoute($index);
		$albums = new Route('/albums', static fn() => null);
		$router->addRoute($albums);
		$router->group('/albums', static function (Group $group): void {
			$ctrl = TestController::class;
			$group->get('/{name}', "{$ctrl}::albumName");
		});

		$this->assertSame('index', $router->match($this->request('GET', '/prefix'))->route()->name());

		$this->assertSame($index, $router->match($this->request('GET', '/prefix'))->route());
		$this->assertSame($albums, $router->match($this->request('GET', '/prefix/albums'))->route());
		$this->assertSame(
			$albums,
			$router->match($this->request('GET', '/prefix/albums?q=Symbolic'))->route(),
		);
		$this->assertSame(
			'',
			$router->match($this->request('GET', '/prefix/albums/name'))->route()->name(),
		);
	}

	public function testPrefixCleanUp(): void
	{
		$router = new Router('prefix');
		$index = new Route('/test', static fn() => null, 'test');
		$router->addRoute($index);

		$this->assertSame('test', $router->match($this->request('GET', '/prefix/test'))->route()->name());
	}

	public function testEmptyPrefixCleanUp(): void
	{
		$router = new Router('/');
		$router->get('/albums', static fn() => null, 'albums');

		$this->assertSame('albums', $router->match($this->request('GET', '/albums'))->route()->name());
		$this->assertSame('/albums', $router->url('albums'));
	}

	public function testThrowingNotFoundException(): void
	{
		$this->throws(NotFoundException::class);

		$router = new Router();
		$router->match($this->request('GET', '/does-not-exist'))->route();
	}

	public function testSimpleMatchingUrlEncoded(): void
	{
		$router = new Router();
		$route = new Route('/album name/...slug', static fn() => null, 'encoded');
		$router->addRoute($route);

		$match = $router->match(
			$this->request('GET', '/album%20name/scream%20bloody%20gore'),
		);

		$this->assertSame('encoded', $match->route()->name());
		$this->assertSame(['slug' => 'scream bloody gore'], $match->params());
	}

	public function testMatchingWithHelpers(): void
	{
		$this->throws(MethodNotAllowedException::class);

		$router = new Router();
		$index = $router->get('/', static fn() => null, 'index');
		$albums = $router->post('/albums', static fn() => null);
		$any = $router->any('/any', static fn() => null, 'any');

		$this->assertSame('index', $router->match($this->request('GET', ''))->route()->name());
		$this->assertSame('', $router->match($this->request('POST', '/albums'))->route()->name());
		$this->assertSame('any', $router->match($this->request('PATCH', '/any'))->route()->name());
		$this->assertSame($index, $router->match($this->request('GET', ''))->route());
		$this->assertSame($albums, $router->match($this->request('POST', '/albums'))->route());
		$this->assertSame($any, $router->match($this->request('DELETE', '/any'))->route());

		$router->match($this->request('GET', '/albums'))->route();
	}

	public function testRepeatedMatchesKeepSeparateParams(): void
	{
		$router = new Router();
		$router->get('/albums/{name}', static fn() => null, 'album');

		$first = $router->match($this->request('GET', '/albums/symbolic'));
		$second = $router->match($this->request('GET', '/albums/leprosy'));

		$this->assertSame(['name' => 'symbolic'], $first->params());
		$this->assertSame(['name' => 'leprosy'], $second->params());
		$this->assertSame($first->route(), $second->route());
	}

	public function testGenerateRouteUrl(): void
	{
		$router = new Router();
		$albums = new Route('albums/{from}/{to}', static fn() => null, 'albums');
		$router->addRoute($albums);

		$this->assertSame('/albums/1990/1995', $router->url('albums', [
			'from' => 1990,
			'to' => 1995,
		]));
		$this->assertSame('/albums/1988/1991', $router->url('albums', [
			'from' => 1988,
			'to' => 1991,
		]));
	}

	public function testGenerateRouteUrlWithPrefixHostAndQuery(): void
	{
		$router = new Router('/cms/');
		$router->get('/albums/{id:\d+}', static fn() => null, 'albums.show');

		$this->assertSame(
			'https://duon.sh/cms/albums/13?page=2&sort=death%20metal',
			$router->url(
				'albums.show',
				['id' => 13],
				query: ['page' => 2, 'sort' => 'death metal', 'empty' => null],
				host: 'https://duon.sh/',
			),
		);
	}

	public function testGenerateRouteUrlWithQueryList(): void
	{
		$router = new Router();
		$router->get('/albums', static fn() => null, 'albums');

		$this->assertSame(
			'/albums?tag%5B0%5D=death&tag%5B1%5D=thrash',
			$router->url('albums', query: ['tag' => ['death', 'thrash']]),
		);
	}

	public function testGenerateRouteUrlWithStringableQuery(): void
	{
		$value = new class() {
			public function __toString(): string
			{
				return 'death metal';
			}
		};
		$router = new Router();
		$router->get('/albums', static fn() => null, 'albums');

		$this->assertSame('/albums?sort=death%20metal', $router->url('albums', query: [
			'sort' => $value,
		]));
	}

	public function testGenerateRouteUrlRejectsNestedQuery(): void
	{
		$this->throws(
			InvalidArgumentException::class,
			'Query parameter must be scalar or a list of scalars',
		);

		$router = new Router();
		$router->get('/albums', static fn() => null, 'albums');
		$router->url('albums', query: ['filters' => ['genre' => 'death']]);
	}

	public function testGenerateRouteUrlRejectsObjectQuery(): void
	{
		$this->throws(
			InvalidArgumentException::class,
			'Query parameter must be scalar or a list of scalars',
		);

		$router = new Router();
		$router->get('/albums', static fn() => null, 'albums');
		$router->url('albums', query: ['sort' => new \stdClass()]);
	}

	public function testFailToGenerateRouteUrl(): void
	{
		$this->throws(RuntimeException::class, 'Route not found');

		$router = new Router();
		$router->url('fantasy');
	}

	#[TestDox('GET matching')]
	public function testGETMatching(): void
	{
		$router = new Router();
		$route = Route::get('/', static fn() => null);
		$router->addRoute($route);

		$this->assertSame($route, $router->match($this->request('GET', '/'))->route());
	}

	#[TestDox('HEAD matching')]
	public function testHEADMatching(): void
	{
		$router = new Router();
		$route = Route::head('/', static fn() => null);
		$router->addRoute($route);

		$this->assertSame($route, $router->match($this->request('HEAD', '/'))->route());
	}

	#[TestDox('PUT matching')]
	public function testPUTMatching(): void
	{
		$router = new Router();
		$route = Route::put('/', static fn() => null);
		$router->addRoute($route);

		$this->assertSame($route, $router->match($this->request('PUT', '/'))->route());
	}

	#[TestDox('POST matching')]
	public function testPOSTMatching(): void
	{
		$router = new Router();
		$route = Route::post('/', static fn() => null);
		$router->addRoute($route);

		$this->assertSame($route, $router->match($this->request('POST', '/'))->route());
	}

	#[TestDox('PATCH matching')]
	public function testPATCHMatching(): void
	{
		$router = new Router();
		$route = Route::patch('/', static fn() => null);
		$router->addRoute($route);

		$this->assertSame($route, $router->match($this->request('PATCH', '/'))->route());
	}

	#[TestDox('DELETE matching')]
	public function testDELETEMatching(): void
	{
		$router = new Router();
		$route = Route::delete('/', static fn() => null);
		$router->addRoute($route);

		$this->assertSame($route, $router->match($this->request('DELETE', '/'))->route());
	}

	#[TestDox('OPTIONS matching')]
	public function testOPTIONSMatching(): void
	{
		$router = new Router();
		$route = Route::options('/', static fn() => null);
		$router->addRoute($route);

		$this->assertSame($route, $router->match($this->request('OPTIONS', '/'))->route());
	}

	public function testMatchingWrongMethod(): void
	{
		$this->throws(MethodNotAllowedException::class);

		$router = new Router();
		$router->addRoute(Route::get('/', static fn() => null));

		$router->match($this->request('POST', '/'));
	}

	public function testMethodNotAllowedListsAllowedMethods(): void
	{
		$router = new Router();
		$router->addRoute(new Route('/', static fn() => null)->method('get', 'get', 'put'));

		try {
			$router->match($this->request('POST', '/'));
			$this->fail('Expected method not allowed exception.');
		} catch (MethodNotAllowedException $e) {
			$this->assertSame(['GET', 'PUT'], $e->allowedMethods());
		}
	}

	#[TestDox('Multiple methods matching I')]
	public function testMultipleMethodsMatchingI(): void
	{
		$this->throws(MethodNotAllowedException::class);

		$router = new Router();
		$route = Route::get('/', static fn() => null)->method('post');
		$router->addRoute($route);

		$this->assertSame($route, $router->match($this->request('GET', '/'))->route());
		$this->assertSame($route, $router->match($this->request('POST', '/'))->route());
		$router->match($this->request('PUT', '/'))->route();
	}

	#[TestDox('Multiple methods matching II')]
	public function testMultipleMethodsMatchingII(): void
	{
		$this->throws(MethodNotAllowedException::class);

		$router = new Router();
		$route = new Route('/', static fn() => null)->method('gEt', 'Put');
		$router->addRoute($route);

		$this->assertSame($route, $router->match($this->request('GET', '/'))->route());
		$this->assertSame($route, $router->match($this->request('PUT', '/'))->route());
		$router->match($this->request('POST', '/'))->route();
	}

	#[TestDox('Multiple methods matching III')]
	public function testMultipleMethodsMatchingIII(): void
	{
		$this->throws(MethodNotAllowedException::class);

		$router = new Router();
		$route = new Route('/', static fn() => null)
			->method('get')
			->method('head');
		$router->addRoute($route);

		$this->assertSame($route, $router->match($this->request('GET', '/'))->route());
		$this->assertSame($route, $router->match($this->request('HEAD', '/'))->route());
		$router->match($this->request('POST', '/'))->route();
	}

	public function testAllMethodsMatching(): void
	{
		$router = new Router();
		$route = new Route('/', static fn() => null);
		$router->addRoute($route);

		$this->assertSame($route, $router->match($this->request('GET', '/'))->route());
		$this->assertSame($route, $router->match($this->request('HEAD', '/'))->route());
		$this->assertSame($route, $router->match($this->request('POST', '/'))->route());
		$this->assertSame($route, $router->match($this->request('PUT', '/'))->route());
		$this->assertSame($route, $router->match($this->request('PATCH', '/'))->route());
		$this->assertSame($route, $router->match($this->request('DELETE', '/'))->route());
		$this->assertSame($route, $router->match($this->request('OPTIONS', '/'))->route());
	}

	public function testSamePatternMultipleMethods(): void
	{
		$this->throws(MethodNotAllowedException::class);

		$router = new Router();
		$puthead = new Route('/', static fn() => null, 'puthead')->method('HEAD', 'Put');
		$router->addRoute($puthead);
		$get = new Route('/', static fn() => null, 'get')->method('GET');
		$router->addRoute($get);

		$this->assertSame($get, $router->match($this->request('GET', '/'))->route());
		$this->assertSame($puthead, $router->match($this->request('PUT', '/'))->route());
		$this->assertSame($puthead, $router->match($this->request('HEAD', '/'))->route());
		$router->match($this->request('POST', '/'))->route();
	}

	public function testDuplicateRouteName(): void
	{
		$this->throws(RuntimeException::class, 'Duplicate route name: index');

		$router = new Router();
		$router->addRoute(new Route('/', static fn() => null, 'index'));
		$router->addRoute(new Route('albums', static fn() => null, 'index'));
	}
}
