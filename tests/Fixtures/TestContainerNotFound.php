<?php

declare(strict_types=1);

namespace Duon\Router\Tests\Fixtures;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;

/**
 * A mock container that throws NotFoundException for specific types.
 */
class TestContainerNotFound implements ContainerInterface
{
	public function __construct(
		private readonly array $notFoundTypes = [],
	) {}

	public function get(string $id): mixed
	{
		if (in_array($id, $this->notFoundTypes, true)) {
			throw new class('Not found: ' . $id) extends RuntimeException implements
				NotFoundExceptionInterface {};
		}

		throw new RuntimeException('Not configured: ' . $id);
	}

	public function has(string $id): bool
	{
		return in_array($id, $this->notFoundTypes, true);
	}
}
