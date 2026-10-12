<?php

declare(strict_types=1);

namespace Sciter\Tests\Integration;

use ArrayIterator;
use FFI;
use InvalidArgumentException;
use JsonSerializable;
use PHPUnit\Framework\Attributes\DataProvider;
use Sciter\Value;

final class ValueTest extends IntegrationTestCase
{
    private Value $value;

    protected function setUp(): void
    {
        $this->value = new Value(self::runtime());
    }

    private function roundTrip(mixed $input): mixed
    {
        $runtime = self::runtime();
        $ffi = $runtime->ffi();
        $api = $runtime->api();

        $storage = $ffi->new('VALUE');
        $ptr = FFI::addr($storage);

        ($api->ValueInit)($ptr);

        try {
            $this->value->fromPhp($ptr, $input);

            return $this->value->toPhp($ptr);
        } finally {
            ($api->ValueClear)($ptr);
        }
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function roundTrippable(): array
    {
        return [
            'null' => [null],
            'true' => [true],
            'false' => [false],
            'int' => [42],
            'negative int' => [-7],
            'string' => ['Hello'],
            'unicode string' => ['héllo 👋'],
            'empty string' => [''],
            'list of ints' => [[10, 20, 30]],
            'list of strings' => [['a', 'b']],
            'mixed list' => [[1, 'two', true, null]],
            'nested lists' => [[[1, 2], [3, 4]]],
            'assoc array' => [['id' => 1, 'name' => 'Hamid', 'active' => true]],
            'nested assoc' => [[
                'user' => ['roles' => ['admin', 'cashier'], 'meta' => ['k' => 'v']],
            ]],
            'assoc with null' => [['nothing' => null, 'x' => 1]],
            'list of maps' => [[['id' => 1], ['id' => 2]]],
        ];
    }

    #[DataProvider('roundTrippable')]
    public function testRoundTrip(mixed $input): void
    {
        self::assertSame($input, $this->roundTrip($input));
    }

    public function testFloatRoundTrip(): void
    {
        self::assertEqualsWithDelta(2.5, $this->roundTrip(2.5), 1e-9);
    }

    public function testAssociativeKeysKeepTheirNames(): void
    {
        $result = $this->roundTrip(['b' => 1, 'a' => 2]);

        self::assertEqualsCanonicalizing(['a', 'b'], array_keys($result));
        self::assertSame(1, $result['b']);
        self::assertSame(2, $result['a']);
    }

    public function testPlainObjectIsSentAsItsPublicProperties(): void
    {
        $object = new class {
            public int $id = 7;
            public array $tags = ['a', 'b'];
            private string $secret = 'hidden';

            public function secret(): string
            {
                return $this->secret;
            }
        };

        self::assertSame(
            ['id' => 7, 'tags' => ['a', 'b']],
            $this->roundTrip($object)
        );
    }

    public function testJsonSerializableWinsOverProperties(): void
    {
        $object = new class implements JsonSerializable {
            public string $internal = 'ignored';

            public function jsonSerialize(): array
            {
                return ['public' => 'yes'];
            }
        };

        self::assertSame(['public' => 'yes'], $this->roundTrip($object));
    }

    public function testTraversableIsIterated(): void
    {
        $object = new ArrayIterator(['a' => 1, 'b' => 2]);

        self::assertSame(['a' => 1, 'b' => 2], $this->roundTrip($object));
    }

    public function testNestedObjectsAreFlattened(): void
    {
        $inner = new class implements JsonSerializable {
            public function jsonSerialize(): array
            {
                return ['city' => 'Kampala'];
            }
        };

        $outer = new class($inner) {
            public function __construct(public object $address) {}
        };

        self::assertSame(
            ['address' => ['city' => 'Kampala']],
            $this->roundTrip($outer)
        );
    }

    public function testUnsupportedTypeIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->roundTrip(fopen('php://memory', 'r'));
    }

    /**
     * Documents a current limitation: an empty array never gets a
     * Sciter type and reads back as null.
     */
    public function testEmptyArrayCurrentlyReadsBackAsNull(): void
    {
        self::assertNull($this->roundTrip([]));
    }
}
