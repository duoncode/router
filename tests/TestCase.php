<?php

declare(strict_types=1);

namespace Duon\Router\Tests;

use Duon\Router\Tests\Fixtures\TestAfterRendererText;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\Uri;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\Http\Message\ServerRequestInterface as Request;

class TestCase extends BaseTestCase
{
	public string $root;

	public function setUp(): void
	{
		$this->root = __DIR__ . '/Fixtures';
	}

	public function throws(string $exception, string $message = null): void
	{
		$this->expectException($exception);

		if ($message) {
			$this->expectExceptionMessage($message);
		}
	}

	public function request(
		?string $method = null,
		?string $uri = null,
	): Request {
		$request = ServerRequestFactory::fromGlobals();

		if ($method) {
			$request = $request->withMethod($method);
		}

		if ($uri) {
			$request = $request->withUri(new Uri($uri));
		}

		return $request;
	}

	public function responseFactory(): ResponseFactory
	{
		return new ResponseFactory();
	}

	public function renderer(): TestAfterRendererText
	{
		return new TestAfterRendererText($this->responseFactory());
	}

	public function fullTrim(string $text): string
	{
		return trim(
			preg_replace(
				'/> </',
				'><',
				preg_replace(
					'/\s+/',
					' ',
					preg_replace('/\n/', '', $text),
				),
			),
		);
	}
}