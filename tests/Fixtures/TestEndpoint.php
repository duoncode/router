<?php

declare(strict_types=1);

namespace Duon\Router\Tests\Fixtures;

class TestEndpoint
{
	public function list(): array
	{
		return [];
	}

	public function get(int $id): array
	{
		return ['get' => $id];
	}

	public function post(): array
	{
		return ['post' => true];
	}

	public function put(int $id): array
	{
		return ['put' => $id];
	}

	public function patch(int $id): array
	{
		return ['patch' => $id];
	}

	public function delete(int $id): array
	{
		return ['delete' => $id];
	}

	public function deleteList(): array
	{
		return ['delete' => 'all'];
	}

	public function options(int $id): array
	{
		return ['options' => $id];
	}

	public function optionsList(): array
	{
		return ['options' => 'all'];
	}

	public function head(int $id): array
	{
		return ['head' => $id];
	}

	public function headList(): array
	{
		return ['head' => 'all'];
	}
}