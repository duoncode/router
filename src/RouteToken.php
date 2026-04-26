<?php

declare(strict_types=1);

namespace Duon\Router;

/** @internal */
final readonly class RouteToken
{
	public const string LITERAL = 'literal';
	public const string PARAMETER = 'parameter';
	public const string REMAINDER = 'remainder';

	private function __construct(
		private string $type,
		private string $value = '',
		private ?string $name = null,
		private ?string $constraint = null,
	) {}

	public static function literal(string $value): self
	{
		return new self(self::LITERAL, $value);
	}

	public static function parameter(string $name, ?string $constraint = null): self
	{
		return new self(self::PARAMETER, name: $name, constraint: $constraint);
	}

	public static function remainder(string $name): self
	{
		return new self(self::REMAINDER, name: $name);
	}

	public function type(): string
	{
		return $this->type;
	}

	public function value(): string
	{
		return $this->value;
	}

	public function name(): ?string
	{
		return $this->name;
	}

	public function constraint(): ?string
	{
		return $this->constraint;
	}
}
