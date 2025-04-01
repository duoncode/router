<?php

declare(strict_types=1);

namespace Duon\Router\Tests;

use Duon\Router\Exception\InvalidArgumentException;
use Duon\Router\Exception\ValueError;
use Duon\Router\Route;
use Duon\Router\Tests\Fixtures\TestMiddleware1;
use Duon\Router\Tests\Fixtures\TestMiddleware2;
use stdClass;

class RouteTest extends TestCase
{
	public function testIndexMatching(): void
	{
		$route = new Route('/', fn() => null);

		$this->assertSame($route, $route->match('/'));
		$this->assertSame(null, $route->match('/rick'));
	}

	public function testSimpleMatching(): void
	{
		$route = new Route('/chuck', fn() => null);

		$this->assertSame($route, $route->match('/chuck'));
		$this->assertSame(null, $route->match('/rick'));
	}

	public function testSimpleMatchingWithoutLeadingSlash(): void
	{
		$route = new Route('chuck/and/rick', fn() => null);

		$this->assertSame($route, $route->match('/chuck/and/rick'));
		$this->assertSame(null, $route->match('/chuck'));
	}

	public function testParameterMatching(): void
	{
		$route = new Route('/album/{name}', fn() => null);

		$this->assertSame($route, $route->match('/album/leprosy'));
		$this->assertSame(['name' => 'leprosy'], $route->args());

		$route = new Route('/contributed/{from}/{to}', fn() => null);

		$this->assertSame($route, $route->match('/contributed/1983/1991'));
		$this->assertSame(['from' => '1983', 'to' => '1991'], $route->args());
	}

	public function testParameterMatchingRegex(): void
	{
		$route = new Route('/contributed/{from:\d+}/{to:\d\d\d}', fn() => null);

		$this->assertSame(null, $route->match('/contributed/1983/1991'));
		$this->assertSame($route, $route->match('/contributed/19937/701'));
		$this->assertSame(['from' => '19937', 'to' => '701'], $route->args());

		$route = new Route('/albums/{from:\d{4}}', fn() => null);
		$this->assertSame($route, $route->match('/albums/1995'));
		$this->assertSame(null, $route->match('/albums/521'));

		$route = new Route('/albums/{from:\d{3,4}}', fn() => null);
		$this->assertSame($route, $route->match('/albums/2001'));
		$this->assertSame($route, $route->match('/albums/127'));
		$this->assertSame(null, $route->match('/albums/13'));

		$route = new Route('/albums/{from:\d{2}}/{to:\d{4,5}}', fn() => null);
		$this->assertSame(null, $route->match('/albums/aa/bbbb'));
		$this->assertSame(null, $route->match('/albums/13/773'));
		$this->assertSame(null, $route->match('/albums/457/1709'));
		$this->assertSame($route, $route->match('/albums/73/5183'));
		$this->assertSame($route, $route->match('/albums/43/93911'));
		$this->assertSame(['from' => '43', 'to' => '93911'], $route->args());

		$route = new Route('/albums{format:\.?(json|xml|)}', fn() => null);
		$this->assertSame($route, $route->match('/albums'));
		$this->assertSame(['format' => ''], $route->args());
		$this->assertSame($route, $route->match('/albums.json'));
		$this->assertSame(['format' => '.json'], $route->args());
		$this->assertSame($route, $route->match('/albums.xml'));
		$this->assertSame(['format' => '.xml'], $route->args());
	}

	public function testParameterMatchingBraceErrorI(): void
	{
		$this->throws(ValueError::class, 'Escaped braces are not allowed');

		// Invalid escaped left braces
		$route = new Route('/contributed/{from:\{\d+}', fn() => null);
		$route->match('/');
	}

	public function testParameterMatchingBraceErrorII(): void
	{
		$this->throws(ValueError::class, 'Escaped braces are not allowed:');

		// Invalid escaped right braces
		$route = new Route('/contributed/{from:\d+\}}', fn() => null);
		$route->match('/');
	}

	public function testParameterMatchingBraceErrorIII(): void
	{
		$this->throws(ValueError::class, 'Unbalanced braces in route pattern:');

		// Invalid unbalanced braces
		$route = new Route('/contributed/{from:\d+{1,2}{}', fn() => null);
		$route->match('/');
	}

	public function testUrlConstructionRegularParameters(): void
	{
		$route = new Route('/contributed/{from:\d+}/{to:\d\d\d}', fn() => null);

		$obj = new class (1991) extends stdClass {
			public function __construct(protected int $val) {}

			public function __toString(): string
			{
				return (string) $this->val;
			}
		};

		$this->assertSame('/contributed/1983/1991', $route->url(['from' => 1983, 'to' => $obj]));
		$this->assertSame('/contributed/1983/1991', $route->url(from: 1983, to: 1991));
	}

	public function testUrlConstructionNoParameters(): void
	{
		$route = new Route('/albums', fn() => null);

		$this->assertSame('/albums', $route->url());
		$this->assertSame('/albums', $route->url(test: 1));
	}

	public function testUrlConstructionInvalidCall(): void
	{
		$this->throws(InvalidArgumentException::class);

		$route = new Route('/albums', fn() => null);
		$route->url(1, 2);
	}

	public function testUrlConstructionInvalidParameters(): void
	{
		$this->throws(InvalidArgumentException::class);

		$route = new Route('/contributed/{from:\d+}/{to:\d\d\d}', fn() => null);
		$route->url(from: 1983, to: []);
	}

	public function testRoutePrefix(): void
	{
		$route = Route::get('/albums', fn() => 'chuck')->prefix(pattern: 'api');
		$this->assertSame($route, $route->match('/api/albums'));

		$route = Route::get('albums', fn() => 'chuck', 'albums')->prefix('api/', 'api::');
		$this->assertSame('api/albums', $route->pattern());
		$this->assertSame('api::albums', $route->name());
		$this->assertSame($route, $route->match('/api/albums'));

		$route = Route::get('albums', fn() => 'chuck', 'albums')->prefix(name: 'api::');
		$this->assertSame($route, $route->match('/albums'));
		$this->assertSame('api::albums', $route->name());
	}

	public function testGetViewClosure(): void
	{
		$route = new Route('/', fn() => 'chuck');

		$this->assertSame('chuck', $route->view()());
	}

	public function testGetViewString(): void
	{
		$route = new Route('/', 'chuck');

		$this->assertSame('chuck', $route->view());
	}

	public function testGetViewArray(): void
	{
		$route = new Route('/', [\Duon\Router\Tests\Fixtures\TestController::class, 'textView']);

		$this->assertSame(['Duon\Router\Tests\Fixtures\TestController', 'textView'], $route->view());
	}

	public function testRouteNameUnnamed(): void
	{
		$route = Route::get('/albums', fn() => 'chuck');

		$this->assertSame('', $route->name());
	}

	public function testRouteNameNamed(): void
	{
		$route = Route::get('/albums', fn() => 'chuck', 'albumroute');

		$this->assertSame('albumroute', $route->name());
	}

	public function testRouteMiddleware(): void
	{
		$route = Route::get('/', fn() => 'chuck');
		$route->middleware(new TestMiddleware1());
		$route->middleware(new TestMiddleware2());
		$middleware = $route->getMiddleware();

		$this->assertInstanceof(TestMiddleware1::class, $middleware[0]);
		$this->assertInstanceof(TestMiddleware2::class, $middleware[1]);
	}
}