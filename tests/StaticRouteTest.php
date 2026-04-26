<?php

declare(strict_types=1);

namespace Duon\Router\Tests;

use Duon\Router\Exception\InvalidArgumentException;
use Duon\Router\Exception\RuntimeException;
use Duon\Router\Router;

class StaticRouteTest extends TestCase
{
	public function testStaticRoutesUnnamed(): void
	{
		$router = new Router();
		$router->addStatic('/static', $this->root . '/public/static');

		$this->assertSame('/static/test.json', $router->staticUrl('/static', 'test.json'));
		$this->assertMatchesRegularExpression('/\?v=[a-f0-9]{8}$/', $router->staticUrl(
			'/static',
			'test.json',
			true,
		));
		$this->assertMatchesRegularExpression('/\?exists=true&v=[a-f0-9]{8}$/', $router->staticUrl(
			'/static',
			'test.json?exists=true',
			true,
		));
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
		$this->assertMatchesRegularExpression('/\?v=[a-f0-9]{8}$/', $router->staticUrl(
			'/static',
			'test.json',
			true,
		));
		$this->assertMatchesRegularExpression('/\?exists=true&v=[a-f0-9]{8}$/', $router->staticUrl(
			'/static',
			'test.json?exists=true',
			true,
		));
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

		new Router()->addStatic('/static', $this->root . '/fantasy/dir');
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

	public function testMissingStaticRootDoesNotAddCachebuster(): void
	{
		$base = sys_get_temp_dir() . '/duon-router-static-' . str_replace('.', '', uniqid('', true));
		$static = $base . '/static';
		mkdir($static, recursive: true);

		try {
			$router = new Router();
			$router->addStatic('/static', $static);
			rmdir($static);

			$this->assertSame('/static/test.json', $router->staticUrl('/static', 'test.json', true));
		} finally {
			if (is_dir($static)) {
				rmdir($static);
			}

			if (is_dir($base)) {
				rmdir($base);
			}
		}
	}

	public function testStaticRouteRejectsNullBytePath(): void
	{
		$this->throws(InvalidArgumentException::class, 'Static path must stay inside static root');

		$router = new Router();
		$router->addStatic('/static', $this->root . '/public/static');
		$router->staticUrl('/static', "test\0.json");
	}

	public function testStaticRouteRejectsTraversalPath(): void
	{
		$this->throws(InvalidArgumentException::class, 'Static path must stay inside static root');

		$router = new Router();
		$router->addStatic('/static', $this->root . '/public/static');
		$router->staticUrl('/static', '../../TestController.php');
	}

	public function testStaticRouteRejectsEncodedTraversalPath(): void
	{
		$this->throws(InvalidArgumentException::class, 'Static path must stay inside static root');

		$router = new Router();
		$router->addStatic('/static', $this->root . '/public/static');
		$router->staticUrl('/static', '%2e%2e/%2e%2e/TestController.php', true);
	}

	public function testStaticRouteDoesNotCacheBustSymlinkEscapes(): void
	{
		if (!function_exists('symlink')) {
			$this->markTestSkipped('Symlinks are not available.');
		}

		$base = sys_get_temp_dir() . '/duon-router-static-' . str_replace('.', '', uniqid('', true));
		$static = $base . '/static';
		$outside = $base . '/outside';
		$staticLink = $static . '/secret.txt';
		$outsideFile = $outside . '/secret.txt';

		mkdir($static, recursive: true);
		mkdir($outside, recursive: true);
		file_put_contents($outsideFile, 'secret');

		try {
			set_error_handler(static fn(): bool => true);

			try {
				$linked = symlink($outsideFile, $staticLink);
			} finally {
				restore_error_handler();
			}

			if (!$linked) {
				$this->markTestSkipped('Could not create symlink.');
			}

			$router = new Router();
			$router->addStatic('/static', $static);

			$this->assertSame('/static/secret.txt', $router->staticUrl('/static', 'secret.txt', true));
		} finally {
			if (is_link($staticLink)) {
				unlink($staticLink);
			}

			if (is_file($outsideFile)) {
				unlink($outsideFile);
			}

			rmdir($static);
			rmdir($outside);
			rmdir($base);
		}
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
