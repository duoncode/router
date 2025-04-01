<?php

declare(strict_types=1);

namespace Duon\Router;

use Psr\Http\Message\ResponseInterface as Response;

interface ResponseWrapper
{
	public function unwrap(): Response;
}