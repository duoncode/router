<?php

declare(strict_types=1);

namespace Celemas\Router\Tests\Fixtures;

class TestInvokableClass
{
	public function __invoke()
	{
		return 'Invokable';
	}
}
