<?php

declare(strict_types=1);

namespace Duon\Router\Tests;

use Duon\Router\Exception\MethodNotAllowedException;
use Duon\Router\Exception\NotFoundException;
use Duon\Router\Exception\RuntimeException;
use Duon\Router\Group;
use Duon\Router\Route;
use Duon\Router\Router;
use Duon\Router\Tests\Fixtures\TestController;
use Duon\Router\Tests\Fixtures\TestEndpoint;
use PHPUnit\Framework\Attributes\TestDox;

class RouterTest extends TestCase
{
	public function testMatching(): void
	{
		$router = new Router();
		$index = new Route('/', fn() => null, 'index');
		$router->addRoute($index);
		$albums = new Route('/albums', fn() => null);
		$router->addRoute($albums);
		$router->addGroup(new Group('/albums', function (Group $group) {
			$ctrl = TestController::class;
			$group->addRoute(Route::get('/{name}', "{$ctrl}::albumName"));
		}));

		$this->assertSame('index', $router->match($this->request('GET', ''))->name());

		$this->assertSame($index, $router->match($this->request('GET', '')));
		$this->assertSame($albums, $router->match($this->request('GET', '/albums')));
		$this->assertSame($albums, $router->match($this->request('GET', '/albums?q=Symbolic')));
		$this->assertSame('', $router->match($this->request('GET', '/albums/name'))->name());
	}

	public function testPrefixMatching(): void
	{
		$router = new Router('/prefix');
		$index = new Route('/', fn() => null, 'index');
		$router->addRoute($index);
		$albums = new Route('/albums', fn() => null);
		$router->addRoute($albums);
		$router->addGroup(new Group('/albums', function (Group $group) {
			$ctrl = TestController::class;
			$group->addRoute(Route::get('/{name}', "{$ctrl}::albumName"));
		}));

		$this->assertSame('index', $router->match($this->request('GET', '/prefix'))->name());

		$this->assertSame($index, $router->match($this->request('GET', '/prefix')));
		$this->assertSame($albums, $router->match($this->request('GET', '/prefix/albums')));
		$this->assertSame($albums, $router->match($this->request('GET', '/prefix/albums?q=Symbolic')));
		$this->assertSame('', $router->match($this->request('GET', '/prefix/albums/name'))->name());
	}

	public function testPrefixCleanUp(): void
	{
		$router = new Router('prefix');
		$index = new Route('/test', fn() => null, 'test');
		$router->addRoute($index);

		$this->assertSame('test', $router->match($this->request('GET', '/prefix/test'))->name());
	}

	public function testThrowingNotFoundException(): void
	{
		$this->throws(NotFoundException::class);

		$router = new Router();
		$router->match($this->request('GET', '/does-not-exist'));
	}

	public function testSimpleMatchingUrlEncoded(): void
	{
		$router = new Router();
		$route = new Route('/album name/...slug', fn() => null, 'encoded');
		$router->addRoute($route);

		$this->assertSame('encoded', $router->match(
			$this->request('GET', '/album%20name/scream%20bloody%20gore'),
		)->name());
		$this->assertSame('scream bloody gore', $route->args()['slug']);
	}

	public function testMatchingWithHelpers(): void
	{
		$this->throws(MethodNotAllowedException::class);

		$router = new Router();
		$index = $router->get('/', fn() => null, 'index');
		$albums = $router->post('/albums', fn() => null);

		$this->assertSame('index', $router->match($this->request('GET', ''))->name());
		$this->assertSame('', $router->match($this->request('POST', '/albums'))->name());
		$this->assertSame($index, $router->match($this->request('GET', '')));
		$this->assertSame($albums, $router->match($this->request('POST', '/albums')));

		$router->match($this->request('GET', '/albums'));
	}

	public function testGenerateRouteUrl(): void
	{
		$router = new Router();
		$albums = new Route('albums/{from}/{to}', fn() => null, 'albums');
		$router->addRoute($albums);

		$this->assertSame('/albums/1990/1995', $router->routeUrl('albums', from: 1990, to: 1995));
		$this->assertSame('/albums/1988/1991', $router->routeUrl('albums', ['from' => 1988, 'to' => 1991]));
	}

	public function testFailToGenerateRouteUrl(): void
	{
		$this->throws(RuntimeException::class, 'Route not found');

		$router = new Router();
		$router->routeUrl('fantasy');
	}

	#[TestDox("GET matching")]
	public function testGETMatching(): void
	{
		$router = new Router();
		$route = Route::get('/', fn() => null);
		$router->addRoute($route);

		$this->assertSame($route, $router->match($this->request('GET', '/')));
	}

	#[TestDox("HEAD matching")]
	public function testHEADMatching(): void
	{
		$router = new Router();
		$route = Route::head('/', fn() => null);
		$router->addRoute($route);

		$this->assertSame($route, $router->match($this->request('HEAD', '/')));
	}

	#[TestDox("PUT matching")]
	public function testPUTMatching(): void
	{
		$router = new Router();
		$route = Route::put('/', fn() => null);
		$router->addRoute($route);

		$this->assertSame($route, $router->match($this->request('PUT', '/')));
	}

	#[TestDox("POST matching")]
	public function testPOSTMatching(): void
	{
		$router = new Router();
		$route = Route::post('/', fn() => null);
		$router->addRoute($route);

		$this->assertSame($route, $router->match($this->request('POST', '/')));
	}

	#[TestDox("PATCH matching")]
	public function testPATCHMatching(): void
	{
		$router = new Router();
		$route = Route::patch('/', fn() => null);
		$router->addRoute($route);

		$this->assertSame($route, $router->match($this->request('PATCH', '/')));
	}

	#[TestDox("DELETE matching")]
	public function testDELETEMatching(): void
	{
		$router = new Router();
		$route = Route::delete('/', fn() => null);
		$router->addRoute($route);

		$this->assertSame($route, $router->match($this->request('DELETE', '/')));
	}

	#[TestDox("OPTIONS matching")]
	public function testOPTIONSMatching(): void
	{
		$router = new Router();
		$route = Route::options('/', fn() => null);
		$router->addRoute($route);

		$this->assertSame($route, $router->match($this->request('OPTIONS', '/')));
	}

	public function testMatchingWrongMethod(): void
	{
		$this->throws(MethodNotAllowedException::class);

		$router = new Router();
		$route = Route::get('/', fn() => null);
		$router->addRoute($route);

		$this->assertSame($route, $router->match($this->request('POST', '/')));
	}

	#[TestDox("Multiple methods matching I")]
	public function testMultipleMethodsMatchingI(): void
	{
		$this->throws(MethodNotAllowedException::class);

		$router = new Router();
		$route = Route::get('/', fn() => null)->method('post');
		$router->addRoute($route);

		$this->assertSame($route, $router->match($this->request('GET', '/')));
		$this->assertSame($route, $router->match($this->request('POST', '/')));
		$router->match($this->request('PUT', '/'));
	}

	#[TestDox("Multiple methods matching II")]
	public function testMultipleMethodsMatchingII(): void
	{
		$this->throws(MethodNotAllowedException::class);

		$router = new Router();
		$route = (new Route('/', fn() => null))->method('gEt', 'Put');
		$router->addRoute($route);

		$this->assertSame($route, $router->match($this->request('GET', '/')));
		$this->assertSame($route, $router->match($this->request('PUT', '/')));
		$router->match($this->request('POST', '/'));
	}

	#[TestDox("Multiple methods matching III")]
	public function testMultipleMethodsMatchingIII(): void
	{
		$this->throws(MethodNotAllowedException::class);

		$router = new Router();
		$route = (new Route('/', fn() => null))->method('get')->method('head');
		$router->addRoute($route);

		$this->assertSame($route, $router->match($this->request('GET', '/')));
		$this->assertSame($route, $router->match($this->request('HEAD', '/')));
		$router->match($this->request('POST', '/'));
	}

	public function testAllMethodsMatching(): void
	{
		$router = new Router();
		$route = new Route('/', fn() => null);
		$router->addRoute($route);

		$this->assertSame($route, $router->match($this->request('GET', '/')));
		$this->assertSame($route, $router->match($this->request('HEAD', '/')));
		$this->assertSame($route, $router->match($this->request('POST', '/')));
		$this->assertSame($route, $router->match($this->request('PUT', '/')));
		$this->assertSame($route, $router->match($this->request('PATCH', '/')));
		$this->assertSame($route, $router->match($this->request('DELETE', '/')));
		$this->assertSame($route, $router->match($this->request('OPTIONS', '/')));
	}

	public function testSamePatternMultipleMethods(): void
	{
		$this->throws(MethodNotAllowedException::class);

		$router = new Router();
		$puthead = (new Route('/', fn() => null, 'puthead'))->method('HEAD', 'Put');
		$router->addRoute($puthead);
		$get = (new Route('/', fn() => null, 'get'))->method('GET');
		$router->addRoute($get);

		$this->assertSame($get, $router->match($this->request('GET', '/')));
		$this->assertSame($puthead, $router->match($this->request('PUT', '/')));
		$this->assertSame($puthead, $router->match($this->request('HEAD', '/')));
		$router->match($this->request('POST', '/'));
	}

	public function testAddEndpoint(): void
	{
		$router = new Router();
		$router->endpoint('/endpoints', TestEndpoint::class, ['id', 'category'])->add();

		$requestRoute = $router->match($this->request('POST', '/endpoints'));
		$this->assertSame('/endpoints', $requestRoute->pattern());
		$this->assertSame([TestEndpoint::class, 'post'], $requestRoute->view());
		$this->assertSame([], $requestRoute->args());
	}

	public function testAddRoutesWithCallback(): void
	{
		$router = new Router();
		$router->routes(function (Router $r): void {
			$r->get('/', fn() => null, 'index');
			$r->post('/albums', fn() => null);
		});

		$this->assertSame('index', $router->match($this->request('GET', ''))->name());
		$this->assertSame('', $router->match($this->request('POST', '/albums'))->name());
	}

	public function testDuplicateRouteName(): void
	{
		$this->throws(RuntimeException::class, 'Duplicate route: index');

		$router = new Router();
		$router->addRoute(new Route('/', fn() => null, 'index'));
		$router->addRoute(new Route('albums', fn() => null, 'index'));
	}
}