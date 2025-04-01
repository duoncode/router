<?php

declare(strict_types=1);

namespace Duon\Router\Tests;

use PHPUnit\Framework\Attributes\TestDox;
use ReflectionFunction;
use ReflectionMethod;

use function Duon\Router\getReflectionFunction;

class UtilsTest extends TestCase
{
	#[TestDox('getRelfectionFunction with Closure')]
	public function testReflectionFunctionClosure(): void
	{
		$rf = getReflectionFunction(function () {});

		$this->assertInstanceOf(ReflectionFunction::class, $rf);
	}

	#[TestDox('getRelfectionFunction with callable class')]
	public function testReflectFunctionClas(): void
	{
		$rf = getReflectionFunction(new class {
			public function __invoke(): string
			{
				return '';
			}
		});

		$this->assertInstanceOf(ReflectionMethod::class, $rf);
	}

	#[TestDox('getRelfectionFunction with function string')]
	public function testReflectFunction(): void
	{
		$rf = getReflectionFunction('is_string');

		$this->assertInstanceOf(ReflectionFunction::class, $rf);
	}
}