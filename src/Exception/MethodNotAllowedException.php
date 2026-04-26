<?php

declare(strict_types=1);

namespace Duon\Router\Exception;

/** @psalm-api */
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

		parent::__construct($message !== '' ? $message : 'Method not allowed');
	}

	/**
	 * @psalm-api
	 * @return list<string>
	 */
	public function allowedMethods(): array
	{
		return $this->allowedMethods;
	}
}
