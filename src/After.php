<?php

declare(strict_types=1);

namespace Celemas\Router;

/** @psalm-api */
interface After
{
	public function handle(mixed $data): mixed;

	public function replace(After $handler): bool;
}
