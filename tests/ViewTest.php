<?php

declare(strict_types=1);

namespace Duon\Router\Tests;

use Duon\Router\Exception\RuntimeException;
use Duon\Router\Route;
use Duon\Router\Tests\Fixtures\TestAttribute;
use Duon\Router\Tests\Fixtures\TestAttributeDiff;
use Duon\Router\Tests\Fixtures\TestAttributeExt;
use Duon\Router\Tests\Fixtures\TestController;
use Duon\Router\Tests\Fixtures\TestControllerWithRequest;
use Duon\Router\Tests\Fixtures\TestControllerWithRequestAndRoute;
use Duon\Router\Tests\Fixtures\TestControllerWithRoute;
use Duon\Router\View;
use GdImage;

class ViewTest extends TestCase
{
	public function testAttribute(): void
	{
		$route = Route::any('/', #[TestAttribute] fn() => 'duon')->after($this->renderer());
		$view = new View($route, null);

		$this->assertInstanceOf(TestAttribute::class, $view->attributes()[0]);
	}

	public function testClosure(): void
	{
		$route = Route::any('/', fn() => 'duon')->after($this->renderer());
		$view = new View($route, null);

		$this->assertSame('duon', (string) $view->execute($this->request())->getBody());
	}

	public function testFunction(): void
	{
		$route = Route::any('/{name}', 'testViewWithAttribute')->after($this->renderer());
		$route->match('/symbolic');
		$view = new View($route, null);

		$this->assertSame('symbolic', (string) $view->execute($this->request())->getBody());
		$this->assertInstanceOf(TestAttribute::class, $view->attributes()[0]);
	}

	public function testControllerString(): void
	{
		$route = Route::any('/', '\Duon\Router\Tests\Fixtures\TestController::textView')->after($this->renderer());
		$view = new View($route, null);

		$this->assertSame('text', (string) $view->execute($this->request())->getBody());
		$this->assertInstanceOf(TestAttribute::class, $view->attributes()[0]);
	}

	public function testControllerClassMethod(): void
	{
		$route = Route::any('/', [TestController::class, 'textView'])->after($this->renderer());
		$view = new View($route, null);

		$this->assertSame('text', (string) $view->execute($this->request())->getBody());
		$this->assertInstanceOf(TestAttribute::class, $view->attributes()[0]);
	}

	public function testControllerObjectMethod(): void
	{
		$controller = new TestController();
		$route = Route::any('/', [$controller, 'textView'])->after($this->renderer());
		$view = new View($route, null);

		$this->assertSame('text', (string) $view->execute($this->request())->getBody());
		$this->assertInstanceOf(TestAttribute::class, $view->attributes()[0]);
	}

	public function testInvokableClass(): void
	{
		$route = Route::any('/', 'Duon\Router\Tests\Fixtures\TestInvokableClass')->after($this->renderer());
		$view = new View($route, null);

		$this->assertSame('Invokable', (string) $view->execute($this->request())->getBody());
	}

	public function testNonexistentControllerView(): void
	{
		$this->throws(RuntimeException::class, 'View method not found');

		$route = Route::any('/', TestController::class . '::nonexistentView')->after($this->renderer());
		$view = new View($route, null);
		$view->execute($this->request());
	}

	public function testNonexistentController(): void
	{
		$this->throws(RuntimeException::class, 'Controller not found');

		$route = Route::any('/', NonexisitentTestController::class . '::nonexistentView')->after($this->renderer());
		$view = new View($route, null);
		$view->execute($this->request());
	}

	public function testControllerWithRequestInConstructor(): void
	{
		$request = $this->request();
		$route = Route::any('/', TestControllerWithRequest::class . '::requestOnly')->after($this->renderer());
		$view = new View($route, null);

		$this->assertSame($request::class, (string) $view->execute($request)->getBody());
	}

	public function testControllerWithRouteInConstructor(): void
	{
		$route = Route::any('/', TestControllerWithRoute::class . '::routeOnly')->after($this->renderer());
		$view = new View($route, null);

		$this->assertSame($route::class, (string) $view->execute($this->request())->getBody());
	}

	public function testControllerWithRequestRouteAndParamInConstructor(): void
	{
		$request = $this->request();
		$route = Route::any(
			'/{param}',
			TestControllerWithRequestAndRoute::class . '::requestAndRoute',
		)->after($this->renderer());
		$route->match('/duon');
		$view = new View($route, null);

		$this->assertSame(
			$request::class . $route::class . 'duon',
			(string) $view->execute($request)->getBody(),
		);
	}

	public function testViewWithRouteParams(): void
	{
		$request = $this->request();
		$route = Route::any(
			'/{string}/{float}-{int}',
			TestControllerWithRequest::class . '::routeParams',
		)->after($this->renderer());
		$route->match('/symbolic/7.13-23');
		$view = new View($route, null);

		$this->assertSame(
			'{"string":"symbolic","float":7.13,"int":23,"request":"Laminas\\\\Diactoros\\\\ServerRequest"}',
			(string) $view->execute($request)->getBody(),
		);
	}

	public function testViewWithDefaultValueParams(): void
	{
		// Should overwrite the default value
		$route = Route::any(
			'/{string}/{int}',
			TestController::class . '::routeDefaultValueParams',
		)->after($this->renderer());
		$route->match('/symbolic/17');
		$view = new View($route, null);

		$this->assertSame('{"string":"symbolic","int":17}', (string) $view->execute($this->request())->getBody());

		// Should use the default value
		$route = Route::any('/{string}', TestController::class . '::routeDefaultValueParams')->after($this->renderer());
		$route->match('/symbolic');
		$view = new View($route, null);

		$this->assertSame('{"string":"symbolic","int":13}', (string) $view->execute($this->request())->getBody());
	}

	public function testViewWithWrongRouteParams(): void
	{
		$this->throws(RuntimeException::class, 'cannot be resolved');

		$route = Route::any(
			'/{wrong}/{param}',
			TestControllerWithRequest::class . '::routeParams',
		)->after($this->renderer());
		$route->match('/symbolic/test');
		$view = new View($route, null);
		$view->execute($this->request());
	}

	public function testViewWithWrongTypeForIntParam(): void
	{
		$this->throws(RuntimeException::class, "Cannot cast 'int' to int");

		$route = Route::any(
			'/{string}/{float}-{int}',
			TestControllerWithRequest::class . '::routeParams',
		)->after($this->renderer());
		$route->match('/symbolic/7.13-wrong');
		$view = new View($route, null);
		$view->execute($this->request());
	}

	public function testViewWithWrongTypeForFloatParam(): void
	{
		$this->throws(RuntimeException::class, "Cannot cast 'float' to float");

		$route = Route::any(
			'/{string}/{float}-{int}',
			TestControllerWithRequest::class . '::routeParams',
		)->after($this->renderer());
		$route->match('/symbolic/wrong-13');
		$view = new View($route, null);
		$view->execute($this->request());
	}

	public function testViewWithUnsupportedParam(): void
	{
		$this->throws(RuntimeException::class, 'Unresolvable: GdImage');

		$route = Route::any('/{name}', fn(GdImage $name) => $name)->after($this->renderer());
		$route->match('/symbolic');
		$view = new View($route, null);
		$view->execute($this->request());
	}

	public function testAttributeFilteringCallableView(): void
	{
		$route = Route::any(
			'/',
			#[TestAttribute, TestAttributeExt, TestAttributeDiff]
			fn() => 'duon',
		)->after($this->renderer());
		$view = new View($route, null);

		$this->assertSame(3, count($view->attributes()));
		$this->assertSame(2, count($view->attributes(TestAttribute::class)));
		$this->assertSame(1, count($view->attributes(TestAttributeExt::class)));
		$this->assertSame(1, count($view->attributes(TestAttributeDiff::class)));
	}

	public function testAttributeFilteringControllerView(): void
	{
		$route = new Route('/', [TestController::class, 'arrayView']);
		$view = new View($route, null);

		$this->assertSame(3, count($view->attributes()));
		$this->assertSame(2, count($view->attributes(TestAttribute::class)));
		$this->assertSame(1, count($view->attributes(TestAttributeExt::class)));
		$this->assertSame(1, count($view->attributes(TestAttributeDiff::class)));
	}
}