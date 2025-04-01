<?php

declare(strict_types=1);

namespace Duon\Router;

use Psr\Http\Message\ServerRequestInterface as Request;

interface Before
{
	public function handle(Request $request): Request;

	public function replace(Before $handler): bool;
}