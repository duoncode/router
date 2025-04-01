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

	protected array $args = [];

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
	public static function any(string $pattern, callable|array|string $view, string $name = ''): static
	{
		return (new self($pattern, $view, $name));
	}

	/** @psalm-param View $view */
	public static function get(string $pattern, callable|array|string $view, string $name = ''): static
	{
		return (new self($pattern, $view, $name))->method('GET');
	}

	/** @psalm-param View $view */
	public static function post(string $pattern, callable|array|string $view, string $name = ''): static
	{
		return (new self($pattern, $view, $name))->method('POST');
	}

	/** @psalm-param View $view */
	public static function put(string $pattern, callable|array|string $view, string $name = ''): static
	{
		return (new self($pattern, $view, $name))->method('PUT');
	}

	/** @psalm-param View $view */
	public static function patch(string $pattern, callable|array|string $view, string $name = ''): static
	{
		return (new self($pattern, $view, $name))->method('PATCH');
	}

	/** @psalm-param View $view */
	public static function delete(string $pattern, callable|array|string $view, string $name = ''): static
	{
		return (new self($pattern, $view, $name))->method('DELETE');
	}

	/** @psalm-param View $view */
	public static function head(string $pattern, callable|array|string $view, string $name = ''): static
	{
		return (new self($pattern, $view, $name))->method('HEAD');
	}

	/** @psalm-param View $view */
	public static function options(string $pattern, callable|array|string $view, string $name = ''): static
	{
		return (new self($pattern, $view, $name))->method('OPTIONS');
	}

	/** @no-named-arguments */
	public function method(string ...$args): static
	{
		$this->methods = array_merge($this->methods ?? [], array_map(fn($m) => strtoupper($m), $args));

		return $this;
	}

	/** @psalm-return list<string> */
	public function methods(): array
	{
		return $this->methods ?? [];
	}

	public function prefix(string $pattern = '', string $name = ''): static
	{
		if (!empty($pattern)) {
			$this->pattern = $pattern . $this->pattern;
		}

		if (!empty($name)) {
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

		throw new ValueError('Cannot add controller to view of type Closure or array. ' .
			'Also, Endpoints cannot be used in a Group which utilises controllers');
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
				if (is_scalar($value) or ($value instanceof Stringable)) {
					// basic variables
					$url = preg_replace(
						'/\{' . (string) $name . '(:.*?)?\}/',
						urlencode((string) $value),
						$url,
					);

					// remainder variables
					$url = preg_replace(
						'/\.\.\.' . (string) $name . '/',
						urlencode((string) $value),
						$url,
					);
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

	public function args(): array
	{
		return $this->args;
	}

	public function pattern(): string
	{
		return $this->pattern;
	}

	public function match(string $url, string $prefix = ''): ?Route
	{
		$pattern = $this->compiledPattern($prefix);

		/**
		 * The previous assert does not satisfy psalm regarding
		 * `preg_match` pattern must be a non-empty-string.
		 *
		 * @psalm-suppress ArgumentTypeCoercion
		 */
		if (preg_match($pattern, $url, $matches)) {
			// Remove integer indexes from array
			$matches = array_filter(
				$matches,
				fn($_, $k) => !is_int($k),
				ARRAY_FILTER_USE_BOTH,
			);

			foreach ($matches as $key => $match) {
				$this->args[$key] = $match;
			}

			return $this;
		}

		return null;
	}

	protected function hideInnerBraces(string $str): string
	{
		if (strpos($str, '\{') || strpos($str, '\}')) {
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

		// Escape forward slashes
		//     /evil/chuck  to \/evil\/chuck
		$pattern = preg_replace('/\//', '\\/', $pattern);

		$pattern = $this->hideInnerBraces($pattern);

		// Convert variables to named group patterns
		//     /evil/{chuck}  to  /evil/(?P<chuck>[\w-]+)
		$pattern = preg_replace('/\{(\w+?)\}/', '(?P<\1>[.\w-]+)', $pattern);

		// Convert variables with custom patterns e.g. {evil:\d+}
		//     /evil/{chuck:\d+}  to  /evil/(?P<chuck>\d+)
		$pattern = preg_replace('/\{(\w+?):(.+?)\}/', '(?P<\1>\2)', $pattern);

		// Convert remainder pattern ...slug to (?P<slug>.*)
		$pattern = preg_replace('/\.\.\.(\w+?)$/', '(?P<\1>.*)', $pattern);

		$pattern = '/^' . $pattern . '$/';

		return $this->restoreInnerBraces($pattern);
	}
}