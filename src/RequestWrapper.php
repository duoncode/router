<?php

declare(strict_types=1);

namespace Duon\Router;

use Psr\Http\Message\ServerRequestInterface as Request;

interface RequestWrapper
{
	public function unwrap(): Request;
}