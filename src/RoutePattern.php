<?php

declare(strict_types=1);

namespace Duon\Router;

use Duon\Router\Exception\InvalidArgumentException;
use Duon\Router\Exception\ValueError;
use Stringable;

/** @internal */
final readonly class RoutePattern
{
	private const string LEFT_BRACE = '§§§€§§§';
	private const string RIGHT_BRACE = '§§§£§§§';

	private string $pattern;

	/** @var list<RouteToken> */
	private array $tokens;

	private string $regex;

	public function __construct(string $pattern)
	{
		$this->pattern = self::normalize($pattern);
		$this->tokens = $this->parse($this->pattern);
		$this->regex = '~^' . $this->compile($this->tokens) . '$~';
	}

	public function pattern(): string
	{
		return $this->pattern;
	}

	/**
	 * @psalm-api
	 * @return list<RouteToken>
	 */
	public function tokens(): array
	{
		return $this->tokens;
	}

	/** @return null|array<string, string> */
	public function match(string $path): ?array
	{
		$path = $path === '' ? '/' : $path;

		/** @psalm-suppress ArgumentTypeCoercion */
		if (preg_match($this->regex, $path, $matches) !== 1) {
			return null;
		}

		/** @var array<string, string> */
		return array_filter(
			$matches,
			static fn($_, $key) => !is_int($key),
			ARRAY_FILTER_USE_BOTH,
		);
	}

	/** @param array<array-key, mixed> $params */
	public function generate(array $params = []): string
	{
		$this->assertKnownParams($params);
		$path = '';

		foreach ($this->tokens as $token) {
			$path .= match ($token->type()) {
				RouteToken::LITERAL => $token->value(),
				RouteToken::PARAMETER => $this->generateParameter($token, $params),
				RouteToken::REMAINDER => $this->generateRemainder($token, $params),
			};
		}

		return $path;
	}

	/** @param array<array-key, mixed> $params */
	private function generateParameter(RouteToken $part, array $params): string
	{
		$name = (string) $part->name();
		$value = $this->stringParam($params, $name);
		$constraint = $part->constraint() ?? '[.\w-]+';

		if (!$this->matchesConstraint($constraint, $value)) {
			throw new InvalidArgumentException('Route parameter does not match constraint: ' . $name);
		}

		return rawurlencode($value);
	}

	/** @param array<array-key, mixed> $params */
	private function generateRemainder(RouteToken $part, array $params): string
	{
		$name = (string) $part->name();
		$value = $this->stringParam($params, $name);
		$this->assertSafeRemainder($name, $value);

		return implode('/', array_map(rawurlencode(...), explode('/', $value)));
	}

	/** @param array<array-key, mixed> $params */
	private function stringParam(array $params, string $name): string
	{
		if (!array_key_exists($name, $params)) {
			throw new InvalidArgumentException('Missing route parameter: ' . $name);
		}

		/** @psalm-suppress MixedAssignment */
		$value = $params[$name];

		if (is_scalar($value) || $value instanceof Stringable) {
			return (string) $value;
		}

		throw new InvalidArgumentException('Route parameter must be scalar or Stringable: ' . $name);
	}

	private function matchesConstraint(string $constraint, string $value): bool
	{
		return preg_match('~^(?:' . str_replace('~', '\\~', $constraint) . ')$~', $value) === 1;
	}

	private function assertSafeRemainder(string $name, string $value): void
	{
		if (str_contains($value, "\0") || str_contains($value, '\\') || str_starts_with($value, '/')) {
			throw new InvalidArgumentException('Remainder route parameter must be a relative path: '
			. $name);
		}

		foreach (explode('/', $value) as $segment) {
			if ($segment === '..') {
				throw new InvalidArgumentException('Remainder route parameter must stay relative: ' . $name);
			}
		}
	}

	/** @param array<array-key, mixed> $params */
	private function assertKnownParams(array $params): void
	{
		$names = array_flip($this->parameterNames());

		foreach ($params as $name => $_) {
			if (!is_string($name) || !array_key_exists($name, $names)) {
				throw new InvalidArgumentException('Unknown route parameter: ' . (string) $name);
			}
		}
	}

	/** @return list<string> */
	private function parameterNames(): array
	{
		$names = [];

		foreach ($this->tokens as $token) {
			$name = $token->name();

			if ($name !== null) {
				$names[] = $name;
			}
		}

		return $names;
	}

	private static function normalize(string $pattern): string
	{
		$pattern = '/' . ltrim($pattern, '/');

		return strlen($pattern) > 1 ? rtrim($pattern, '/') : $pattern;
	}

	/** @return list<RouteToken> */
	private function parse(string $pattern): array
	{
		$pattern = $this->hideInnerBraces($pattern);
		$tokens = [];
		$names = [];
		$literal = '';
		$offset = 0;
		$length = strlen($pattern);

		while ($offset < $length) {
			if (preg_match('/\G\{(\w+)(?::([^}]+))?\}/', $pattern, $matches, 0, $offset) === 1) {
				$this->flushLiteral($tokens, $literal);
				$name = $matches[1];
				$this->assertUniqueName($names, $name);
				$constraint = isset($matches[2]) ? $this->restoreInnerBraces($matches[2]) : null;
				$tokens[] = RouteToken::parameter($name, $constraint);
				$offset += strlen($matches[0]);

				continue;
			}

			if (preg_match('/\G\.\.\.(\w+)\z/', $pattern, $matches, 0, $offset) === 1) {
				$this->flushLiteral($tokens, $literal);
				$name = $matches[1];
				$this->assertUniqueName($names, $name);
				$tokens[] = RouteToken::remainder($name);
				$offset += strlen($matches[0]);

				continue;
			}

			$literal .= $pattern[$offset];
			$offset++;
		}

		$this->flushLiteral($tokens, $literal);

		return $tokens;
	}

	/**
	 * @param list<RouteToken> $tokens
	 * @param-out list<RouteToken> $tokens
	 */
	private function flushLiteral(array &$tokens, string &$literal): void
	{
		if ($literal === '') {
			return;
		}

		$tokens[] = RouteToken::literal($literal);
		$literal = '';
	}

	/** @param array<string, true> $names */
	private function assertUniqueName(array &$names, string $name): void
	{
		if (array_key_exists($name, $names)) {
			throw new ValueError('Duplicate route parameter: ' . $name);
		}

		$names[$name] = true;
	}

	private function hideInnerBraces(string $str): string
	{
		if (str_contains($str, '\\{') || str_contains($str, '\\}')) {
			throw new ValueError('Escaped braces are not allowed: ' . $this->pattern);
		}

		$new = '';
		$level = 0;

		foreach (str_split($str) as $c) {
			if ($c === '{') {
				$level++;
				$new .= $level > 1 ? self::LEFT_BRACE : '{';

				continue;
			}

			if ($c === '}') {
				$new .= $level > 1 ? self::RIGHT_BRACE : '}';
				$level--;

				continue;
			}

			$new .= $c;
		}

		if ($level !== 0) {
			throw new ValueError('Unbalanced braces in route pattern: ' . $this->pattern);
		}

		return $new;
	}

	private function restoreInnerBraces(string $str): string
	{
		return str_replace(self::LEFT_BRACE, '{', str_replace(self::RIGHT_BRACE, '}', $str));
	}

	/** @param list<RouteToken> $tokens */
	private function compile(array $tokens): string
	{
		$regex = '';

		foreach ($tokens as $token) {
			$regex .= match ($token->type()) {
				RouteToken::LITERAL => preg_quote($token->value(), '~'),
				RouteToken::PARAMETER => $this->compileParameter($token),
				RouteToken::REMAINDER => $this->compileRemainder($token),
			};
		}

		return $regex;
	}

	private function compileParameter(RouteToken $part): string
	{
		$name = (string) $part->name();
		$constraint = $part->constraint();

		return $constraint === null
			? "(?P<{$name}>[.\w-]+)"
			: "(?P<{$name}>" . str_replace('~', '\\~', $constraint) . ')';
	}

	private function compileRemainder(RouteToken $part): string
	{
		return '(?P<' . (string) $part->name() . '>.*)';
	}
}
