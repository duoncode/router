<?php

declare(strict_types=1);

namespace Duon\Router\Tests\Fixtures;

class TestInvokableClass
{
	public function __invoke()
	{
		return 'Invokable';
	}
}