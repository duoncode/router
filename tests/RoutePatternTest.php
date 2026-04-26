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
