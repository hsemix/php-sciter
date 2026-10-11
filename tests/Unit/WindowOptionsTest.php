<?php

declare(strict_types=1);

namespace Sciter\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sciter\WindowOptions;

final class WindowOptionsTest extends TestCase
{
    public function testDefaults(): void
    {
        $options = new WindowOptions();

        self::assertSame(900, $options->width);
        self::assertSame(600, $options->height);
        self::assertSame(100, $options->x);
        self::assertSame(100, $options->y);
        self::assertTrue($options->main);
        self::assertTrue($options->resizable);
        self::assertTrue($options->titlebar);
        self::assertTrue($options->controls);
    }

    public function testNamedArgumentsAreStored(): void
    {
        $options = new WindowOptions(
            width: 300,
            height: 200,
            x: 5,
            y: 6,
            main: false,
            resizable: false,
            titlebar: false,
            controls: false,
        );

        self::assertSame(300, $options->width);
        self::assertSame(200, $options->height);
        self::assertSame(5, $options->x);
        self::assertSame(6, $options->y);
        self::assertFalse($options->main);
        self::assertFalse($options->resizable);
        self::assertFalse($options->titlebar);
        self::assertFalse($options->controls);
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function invalidSizes(): array
    {
        return [
            'zero width' => [0, 100],
            'zero height' => [100, 0],
            'negative width' => [-1, 100],
            'negative height' => [100, -1],
        ];
    }

    #[DataProvider('invalidSizes')]
    public function testRejectsNonPositiveSize(int $width, int $height): void
    {
        $this->expectException(InvalidArgumentException::class);

        new WindowOptions(width: $width, height: $height);
    }
}
