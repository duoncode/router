<?php

declare(strict_types=1);

namespace Duon\Router\Exception;

final class MethodNotAllowedException extends RuntimeException
{
	/** @var list<string> */
	private array $allowedMethods;

	/** @param list<string> $allowedMethods */
	public function __construct(array $allowedMethods, string $message = '')
	{
		$this->allowedMethods = array_values(array_unique(array_map(
			static fn(string $method): string => strtoupper($method),
			$allowedMethods,
		)));

		parent::__construct($message ?: 'Method not allowed');
	}

	/** @return list<string> */
	public function allowedMethods(): array
	{
		return $this->allowedMethods;
	}
}
