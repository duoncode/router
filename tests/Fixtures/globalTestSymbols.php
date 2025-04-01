<?php

declare(strict_types=1);

use Duon\Router\Tests\Fixtures\TestAttribute;

if (!function_exists('testJsonRendererIterator')) {
	function testJsonRendererIterator()
	{
		$arr = [13, 31, 73];

		foreach ($arr as $a) {
			yield $a;
		}
	}

	#[TestAttribute]
	function testViewWithAttribute(string $name): string
	{
		return $name;
	}
}