<?php

declare(strict_types=1);

namespace Duon\Router\Tests\Fixtures;

use Duon\Router\After;
use Psr\Http\Message\ResponseInterface as Response;
use RuntimeException;

class TestAfterAddHeader implements After
{
	public function handle(mixed $data): Response
	{
		if (!($data instanceof Response)) {
			throw new RuntimeException('Must be a response');
		}

		return $data->withHeader('added-header', 'header-value');
	}

	public function replace(After $handler): bool
	{
		return false;
	}
}