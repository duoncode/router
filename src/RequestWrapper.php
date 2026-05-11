<?php

declare(strict_types=1);

namespace Celemas\Router;

use Psr\Http\Message\ServerRequestInterface as Request;

/** @psalm-api */
interface RequestWrapper
{
	public function unwrap(): Request;
}
