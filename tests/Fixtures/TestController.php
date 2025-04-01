<?php

declare(strict_types=1);

namespace Duon\Router\Tests\Fixtures;

use Psr\Http\Message\ResponseFactoryInterface as Factory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class TestController
{
	#[TestAttribute]
	public function textView(): string
	{
		return 'text';
	}

	public function stringableView(): TestClass
	{
		return new TestClass();
	}

	#[TestAttribute, TestAttributeExt, TestAttributeDiff]
	public function arrayView(): array
	{
		return ['success' => true];
	}

	public function middlewareView(Factory $factory): Response
	{
		$response = $factory->createResponse->withHeader('Content-Type', 'text/html');
		$response->getBody()->write('view');

		return $response;
	}

	#[TestMiddleware1]
	public function attributedMiddlewareView(Factory $factory): Response
	{
		$response = $factory->createResponse->withHeader('Content-Type', 'text/html');
		$response->getBody()->write(' attribute-string');

		return $response;
	}

	public function routeParams(string $string, float $float, Request $request, int $int): array
	{
		return [
			'string' => $string,
			'float' => $float,
			'int' => $int,
			'request' => $request::class,
		];
	}

	public function routeDefaultValueParams(string $string, int $int = 13): string
	{
		return json_encode([
			'string' => $string,
			'int' => $int,
		]);
	}

	public function wrongReturnType(): mixed
	{
		return stream_context_create();
	}
}