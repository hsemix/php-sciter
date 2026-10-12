<?php

declare(strict_types=1);

namespace Sciter\Tests\Unit;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use Sciter\Attributes\Expose;
use Sciter\Som\ExposedMethodResolver;

final class ExposedMethodResolverTest extends TestCase
{
    public function testResolvesOnlyMarkedMethods(): void
    {
        $object = new class {
            #[Expose]
            public function greet(string $name): string
            {
                return "Hello, {$name}!";
            }

            public function hidden(): string
            {
                return 'nope';
            }
        };

        $methods = (new ExposedMethodResolver())->resolve($object);

        self::assertSame(['greet'], array_keys($methods));
        self::assertSame('Hello, Hamid!', $methods['greet']('Hamid'));
    }

    public function testAttributeCanRenameMethod(): void
    {
        $object = new class {
            #[Expose('sum')]
            public function add(int $a, int $b): int
            {
                return $a + $b;
            }
        };

        $methods = (new ExposedMethodResolver())->resolve($object);

        self::assertSame(['sum'], array_keys($methods));
        self::assertSame(3, $methods['sum'](1, 2));
    }

    public function testClosuresAreBoundToTheObject(): void
    {
        $object = new class {
            private int $counter = 0;

            #[Expose]
            public function increment(): int
            {
                return ++$this->counter;
            }
        };

        $methods = (new ExposedMethodResolver())->resolve($object);

        self::assertSame(1, $methods['increment']());
        self::assertSame(2, $methods['increment']());
    }

    public function testRejectsObjectWithoutExposedMethods(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no methods marked with #[Expose]');

        (new ExposedMethodResolver())->resolve(new class {
            public function plain(): void {}
        });
    }

    public function testRejectsStaticExposedMethod(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('public and non-static');

        (new ExposedMethodResolver())->resolve(new class {
            #[Expose]
            public static function nope(): void {}
        });
    }

    public function testRejectsNonPublicExposedMethod(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('public and non-static');

        (new ExposedMethodResolver())->resolve(new class {
            #[Expose]
            protected function nope(): void {}
        });
    }

    public function testRejectsDuplicateExposedNames(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Duplicate exposed method 'same'");

        (new ExposedMethodResolver())->resolve(new class {
            #[Expose('same')]
            public function first(): void {}

            #[Expose('same')]
            public function second(): void {}
        });
    }
}
