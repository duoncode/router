<?php

declare(strict_types=1);

namespace Celemas\Router\Tests\Fixtures;

use Attribute;
use Celemas\Wire\Call;

#[Attribute]
#[Call('init')]
class TestCallableAttribute
{
	public bool $initialized = false;

	public function init(): void
	{
		$this->initialized = true;
	}
}
