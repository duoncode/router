<?php

declare(strict_types=1);

namespace Duon\Router\Tests\Fixtures;

use Duon\Router\Before;
use Psr\Http\Message\ServerRequestInterface as Request;

class TestBeforeFirst implements Before
{
	public function handle(Request $request): Request
	{
		return $request->withAttribute('first', 'first-value');
	}

	public function replace(Before $handler): bool
	{
		return $handler instanceof TestBeforeReplace;
	}
}