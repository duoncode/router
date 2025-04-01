<?php

declare(strict_types=1);

namespace Duon\Router\Tests\Fixtures;

use Psr\Http\Message\ServerRequestInterface as Request;

class TestControllerWithRequest
{
	public function __construct(protected Request $request) {}

	public function requestOnly(): string
	{
		return $this->request::class;
	}

	public function routeParams(string $string, float $float, int $int): string
	{
		return json_encode([
			'string' => $string,
			'float' => $float,
			'int' => $int,
			'request' => $this->request::class,
		]);
	}
}