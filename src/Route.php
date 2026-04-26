<?php

declare(strict_types=1);

namespace Duon\Router;

use Closure;
use Duon\Router\Exception\InvalidArgumentException;
use Duon\Router\Exception\ValueError;
use Stringable;

/**
 * @psalm-api
 *
 * @psalm-type View = callable|list{string, string}|non-empty-string
 */
class Route
{
	use AddsBeforeAfter;
	use AddsMiddleware;

	protected const string LEFT_BRACE = '§§§€§§§';
	protected const string RIGHT_BRACE = '§§§£§§§';

	/** @psalm-var null|list<string> */
	protected ?array $methods = null;

	/** @psalm-var Closure|list{string, string}|string */
	protected Closure|array|string $view;

	/**
	 * @param string $pattern The URL pattern of the route
	 *
	 * @psalm-param View $view The callable view. Can be a closure, an invokable object or any other callable
	 *
	 * @param string $name The name of the route. If not given the pattern will be hashed and used as name.
	 */
	public function __construct(
		protected string $pattern,
		callable|array|string $view,
		protected string $name = '',
	) {
		if (is_callable($view)) {
			$this->view = Closure::fromCallable($view);
		} else {
			$this->view = $view;
		}
	}

	/** @psalm-param View $view */
	public static function any(string $pattern, callable|array|string $view, string $name = ''): self
	{
		return new self($pattern, $view, $name);
	}

	/** @psalm-param View $view */
	public static function get(string $pattern, callable|array|string $view, string $name = ''): self
	{
		return new self($pattern, $view, $name)->method('GET');
	}

	/** @psalm-param View $view */
	public static function post(string $pattern, callable|array|string $view, string $name = ''): self
	{
		return new self($pattern, $view, $name)->method('POST');
	}

	/** @psalm-param View $view */
	public static function put(string $pattern, callable|array|string $view, string $name = ''): self
	{
		return new self($pattern, $view, $name)->method('PUT');
	}

	/** @psalm-param View $view */
	public static function patch(string $pattern, callable|array|string $view, string $name = ''): self
	{
		return new self($pattern, $view, $name)->method('PATCH');
	}

	/** @psalm-param View $view */
	public static function delete(
		string $pattern,
		callable|array|string $view,
		string $name = '',
	): self {
		return new self($pattern, $view, $name)->method('DELETE');
	}

	/** @psalm-param View $view */
	public static function head(string $pattern, callable|array|string $view, string $name = ''): self
	{
		return new self($pattern, $view, $name)->method('HEAD');
	}

	/** @psalm-param View $view */
	public static function options(
		string $pattern,
		callable|array|string $view,
		string $name = '',
	): self {
		return new self($pattern, $view, $name)->method('OPTIONS');
	}

	/** @no-named-arguments */
	public function method(string ...$args): static
	{
		$this->methods = array_merge($this->methods ?? [], array_map(static fn($m) => strtoupper(
			$m,
		), $args));

		return $this;
	}

	/** @psalm-return list<string> */
	public function methods(): array
	{
		return $this->methods ?? [];
	}

	public function prefix(string $pattern = '', string $name = ''): static
	{
		if ($pattern !== '') {
			$this->pattern = $pattern . $this->pattern;
		}

		if ($name !== '') {
			$this->name = $name . $this->name;
		}

		return $this;
	}

	/**
	 * Simply prefixes the current $this->view string with $controller.
	 */
	public function controller(string $controller): static
	{
		if (is_string($this->view)) {
			$this->view = [$controller, $this->view];

			return $this;
		}

		throw new ValueError(
			'Cannot add controller to view of type Closure or array. '
			. 'Also, Endpoints cannot be used in a Group which utilises controllers',
		);
	}

	public function name(): string
	{
		return $this->name;
	}

	/**
	 * @psalm-suppress MixedAssignment
	 *
	 * Types are checked in the body.
	 */
	public function url(mixed ...$args): string
	{
		$url = '/' . ltrim($this->pattern, '/');

		if (count($args) > 0) {
			if (is_array($args[0] ?? null)) {
				$args = $args[0];
			} else {
				// Check if args is an associative array
				if (array_keys($args) === range(0, count($args) - 1)) {
					throw new InvalidArgumentException(
						'Route::url: either pass an associative array or named arguments',
					);
				}
			}

			/**
			 * @psalm-suppress MixedAssignment
			 *
			 * We check if $value can be transformed into a string, Psalm
			 * complains anyway.
			 */
			foreach ($args as $name => $value) {
				// TODO: throw error if args do not match url params
				if (is_scalar($value) or $value instanceof Stringable) {
					// basic variables
					$replaced = preg_replace(
						'/\{' . (string) $name . '(:.*?)?\}/',
						urlencode((string) $value),
						$url,
					);
					$url = $replaced ?? $url;

					// remainder variables
					$replaced = preg_replace(
						'/\.\.\.' . (string) $name . '/',
						urlencode((string) $value),
						$url,
					);
					$url = $replaced ?? $url;
				} else {
					throw new InvalidArgumentException('No valid url argument');
				}
			}
		}

		return $url;
	}

	/** @psalm-return Closure|list{string, string}|string */
	public function view(): Closure|array|string
	{
		return $this->view;
	}

	public function pattern(): string
	{
		return $this->pattern;
	}

	public function match(string $url, string $prefix = ''): ?Route
	{
		return $this->matchPath($url, $prefix) === null ? null : $this;
	}

	/** @return null|array<string, string> */
	public function matchPath(string $url, string $prefix = ''): ?array
	{
		$pattern = $this->compiledPattern($prefix);

		/**
		 * The previous assert does not satisfy psalm regarding
		 * `preg_match` pattern must be a non-empty-string.
		 *
		 * @psalm-suppress ArgumentTypeCoercion
		 */
		if (preg_match($pattern, $url, $matches)) {
			/** @var array<string, string> */
			return array_filter(
				$matches,
				static fn($_, $key) => !is_int($key),
				ARRAY_FILTER_USE_BOTH,
			);
		}

		return null;
	}

	protected function hideInnerBraces(string $str): string
	{
		if (str_contains($str, '\{') || str_contains($str, '\}')) {
			throw new ValueError('Escaped braces are not allowed: ' . $this->pattern);
		}

		$new = '';
		$level = 0;

		foreach (str_split($str) as $c) {
			if ($c === '{') {
				$level++;

				if ($level > 1) {
					$new .= self::LEFT_BRACE;
				} else {
					$new .= '{';
				}

				continue;
			}

			if ($c === '}') {
				if ($level > 1) {
					$new .= self::RIGHT_BRACE;
				} else {
					$new .= '}';
				}

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

	protected function restoreInnerBraces(string $str): string
	{
		return str_replace(self::LEFT_BRACE, '{', str_replace(self::RIGHT_BRACE, '}', $str));
	}

	/* TODO: improve prefix handling. Get rid of the many trim calls */
	protected function compiledPattern(string $prefix): string
	{
		// Ensure leading slash, $prefix is already cleaned up by Router
		$pattern = $prefix . '/' . ltrim($this->pattern, '/');

		if (strlen($pattern) > 1) {
			$pattern = rtrim($pattern, '/');
		}

		$pattern = $this->hideInnerBraces($pattern);

		return '~^' . $this->compilePatternTokens($pattern) . '$~';
	}

	private function compilePatternTokens(string $pattern): string
	{
		$regex = '';
		$offset = 0;
		$length = strlen($pattern);

		while ($offset < $length) {
			if (preg_match('/\G\{(\w+)(?::([^}]+))?\}/', $pattern, $matches, 0, $offset) === 1) {
				$name = $matches[1];
				$customPattern = $matches[2] ?? null;

				$regex .= $customPattern === null
					? "(?P<{$name}>[.\w-]+)"
					: "(?P<{$name}>" . str_replace('~', '\\~', $this->restoreInnerBraces($customPattern)) . ')';
				$offset += strlen($matches[0]);

				continue;
			}

			if (preg_match('/\G\.\.\.(\w+)\z/', $pattern, $matches, 0, $offset) === 1) {
				$regex .= "(?P<{$matches[1]}>.*)";
				$offset += strlen($matches[0]);

				continue;
			}

			$regex .= preg_quote($pattern[$offset], '~');
			$offset++;
		}

		return $regex;
	}
}
