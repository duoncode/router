<?php

declare(strict_types=1);

namespace Celemas\Router;

use Psr\Http\Message\ServerRequestInterface as Request;

/** @psalm-api */
interface Before
{
	public function handle(Request $request): Request;

	public function replace(Before $handler): bool;
}
