<?php

declare(strict_types=1);

namespace Duon\Router;

use Duon\Router\Exception\ValueError;

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

	/** @return list<RouteToken> */
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

	private function compileParameter(RouteToken $token): string
	{
		$name = (string) $token->name();
		$constraint = $token->constraint();

		return $constraint === null
			? "(?P<{$name}>[.\w-]+)"
			: "(?P<{$name}>" . str_replace('~', '\\~', $constraint) . ')';
	}

	private function compileRemainder(RouteToken $token): string
	{
		return '(?P<' . (string) $token->name() . '>.*)';
	}
}
