<?php

declare(strict_types=1);

namespace Duon\Router\Tests\Fixtures;

use Psr\Http\Message\ResponseInterface as Response;

class TestAfterRendererJson extends TestAfterRendererText
{
	public function handle(mixed $data): Response
	{
		$response = $this->responseFactory->createResponse()->withHeader('Content-Type', 'application/json');
		$response->getBody()->write(json_encode($data));

		return $response;
	}
}