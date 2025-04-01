<?php

declare(strict_types=1);

namespace Duon\Router\Tests;

use Duon\Router\Exception\RuntimeException;
use Duon\Router\Router;

class StaticRouteTest extends TestCase
{
	public function testStaticRoutesUnnamed(): void
	{
		$router = new Router();
		$router->addStatic('/static', $this->root . '/public/static');

		$this->assertSame('/static/test.json', $router->staticUrl('/static', 'test.json'));
		$this->assertMatchesRegularExpression('/\?v=[a-f0-9]{8}$/', $router->staticUrl('/static', 'test.json', true));
		$this->assertMatchesRegularExpression('/\?exists=true&v=[a-f0-9]{8}$/', $router->staticUrl('/static', 'test.json?exists=true', true));
		$this->assertMatchesRegularExpression(
			'/https:\/\/duon.local\/static\/test.json\?v=[a-f0-9]{8}$/',
			$router->staticUrl(
				'/static',
				'test.json',
				host: 'https://duon.local/',
				bust: true,
			),
		);
	}

	public function testStaticRoutesUnnamedPrefixed(): void
	{
		$router = new Router('/prefix');
		$router->addStatic('/static', $this->root . '/public/static');

		$this->assertSame('/prefix/static/test.json', $router->staticUrl('/static', 'test.json'));
		$this->assertMatchesRegularExpression('/\?v=[a-f0-9]{8}$/', $router->staticUrl('/static', 'test.json', true));
		$this->assertMatchesRegularExpression('/\?exists=true&v=[a-f0-9]{8}$/', $router->staticUrl('/static', 'test.json?exists=true', true));
		$this->assertMatchesRegularExpression(
			'/https:\/\/duon.local\/prefix\/static\/test.json\?v=[a-f0-9]{8}$/',
			$router->staticUrl(
				'/static',
				'test.json',
				host: 'https://duon.local/',
				bust: true,
			),
		);
	}

	public function testStaticRoutesNamed(): void
	{
		$router = new Router();
		$router->addStatic('/static', $this->root . '/public/static', 'staticroute');

		$this->assertSame('/static/test.json', $router->staticUrl('staticroute', 'test.json'));
	}

	public function testStaticRoutesPrefixed(): void
	{
		$router = new Router('/prefix');
		$router->addStatic('/static', $this->root . '/public/static', 'staticroute');

		$this->assertSame('/prefix/static/test.json', $router->staticUrl('staticroute', 'test.json'));
	}

	public function testStaticRoutesToNonexistentDirectory(): void
	{
		$this->throws(RuntimeException::class, 'does not exist');

		(new Router())->addStatic('/static', $this->root . '/fantasy/dir');
	}

	public function testNonExistingFilesNoCachebuster(): void
	{
		$router = new Router();
		$router->addStatic('/static', $this->root . '/public/static');

		// Non existing files should not have a cachebuster attached
		$this->assertMatchesRegularExpression('/https:\/\/duon.local\/static\/does-not-exist.json$/', $router->staticUrl(
			'/static',
			'does-not-exist.json',
			host: 'https://duon.local/',
			bust: true,
		));
	}

	public function testStaticRouteDuplicateNamed(): void
	{
		$this->throws(RuntimeException::class, 'Duplicate static route: static');

		$router = new Router();
		$router->addStatic('/static', $this->root . '/public/static', 'static');
		$router->addStatic('/anotherstatic', $this->root . '/public/static', 'static');
	}

	public function testStaticRouteDuplicateUnnamed(): void
	{
		$this->throws(RuntimeException::class, 'Duplicate static route: /static');

		$router = new Router();
		$router->addStatic('/static', $this->root . '/public/static');
		$router->addStatic('/static', $this->root . '/public/static');
	}
}