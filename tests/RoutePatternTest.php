<?php

declare(strict_types=1);

namespace Duon\Router\Tests;

use Duon\Router\Exception\ValueError;
use Duon\Router\RoutePattern;
use Duon\Router\RouteToken;

final class RoutePatternTest extends TestCase
{
	public function testTokens(): void
	{
		$pattern = new RoutePattern('/albums/{year:\d{2,4}}/...slug');
		$tokens = $pattern->tokens();

		$this->assertCount(4, $tokens);
		$this->assertSame(RouteToken::LITERAL, $tokens[0]->type());
		$this->assertSame('/albums/', $tokens[0]->value());
		$this->assertSame(RouteToken::PARAMETER, $tokens[1]->type());
		$this->assertSame('year', $tokens[1]->name());
		$this->assertSame('\d{2,4}', $tokens[1]->constraint());
		$this->assertSame(RouteToken::LITERAL, $tokens[2]->type());
		$this->assertSame('/', $tokens[2]->value());
		$this->assertSame(RouteToken::REMAINDER, $tokens[3]->type());
		$this->assertSame('slug', $tokens[3]->name());
	}

	public function testMatch(): void
	{
		$pattern = new RoutePattern('/albums/{year:\d{4}}/...slug');

		$this->assertSame(
			['year' => '1991', 'slug' => 'death/human'],
			$pattern->match('/albums/1991/death/human'),
		);
		$this->assertNull($pattern->match('/albums/nineteen/death/human'));
	}

	public function testGeneratePath(): void
	{
		$pattern = new RoutePattern('/albums/{year:\d{4}}/{name}');

		$this->assertSame(
			'/albums/1991/human',
			$pattern->generate(['year' => 1991, 'name' => 'human']),
		);
	}

	public function testGenerateEncodesOrdinaryParameters(): void
	{
		$pattern = new RoutePattern('/files/{name:.+}');

		$this->assertSame('/files/death%2Fhuman%20tones', $pattern->generate([
			'name' => 'death/human tones',
		]));
	}

	public function testGenerateRemainderPreservesSlashes(): void
	{
		$pattern = new RoutePattern('/files/...slug');

		$this->assertSame('/files/albums/death%20metal.json', $pattern->generate([
			'slug' => 'albums/death metal.json',
		]));
	}

	public function testGenerateRequiresParams(): void
	{
		$this->throws(
			\Duon\Router\Exception\InvalidArgumentException::class,
			'Missing route parameter: id',
		);

		new RoutePattern('/albums/{id}')->generate();
	}

	public function testGenerateRejectsUnknownParams(): void
	{
		$this->throws(
			\Duon\Router\Exception\InvalidArgumentException::class,
			'Unknown route parameter: page',
		);

		new RoutePattern('/albums')->generate(['page' => 2]);
	}

	public function testGenerateRejectsInvalidParamTypes(): void
	{
		$this->throws(
			\Duon\Router\Exception\InvalidArgumentException::class,
			'Route parameter must be scalar or Stringable: id',
		);

		new RoutePattern('/albums/{id}')->generate(['id' => []]);
	}

	public function testGenerateRejectsConstraintMismatch(): void
	{
		$this->throws(
			\Duon\Router\Exception\InvalidArgumentException::class,
			'Route parameter does not match constraint: id',
		);

		new RoutePattern('/albums/{id:\d+}')->generate(['id' => 'abc']);
	}

	public function testGenerateRejectsUnsafeRemainder(): void
	{
		$this->throws(
			\Duon\Router\Exception\InvalidArgumentException::class,
			'Remainder route parameter must stay relative: slug',
		);

		new RoutePattern('/files/...slug')->generate(['slug' => '../secret.txt']);
	}

	public function testRejectDuplicateParameterNames(): void
	{
		$this->throws(ValueError::class, 'Duplicate route parameter: id');

		new RoutePattern('/albums/{id}/songs/{id}');
	}

	public function testRejectDuplicateRemainderNames(): void
	{
		$this->throws(ValueError::class, 'Duplicate route parameter: slug');

		new RoutePattern('/files/{slug}/...slug');
	}
}
