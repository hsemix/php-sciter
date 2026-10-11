<?php

declare(strict_types=1);

namespace Sciter\Tests\Integration;

use InvalidArgumentException;
use Sciter\Runtime;

final class RuntimeExposeTest extends IntegrationTestCase
{
    private Runtime $runtime;

    protected function setUp(): void
    {
        $this->runtime = self::runtime();
    }

    public function testRejectsEmptyName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('name cannot be empty');

        $this->runtime->expose('', ['a' => fn() => 1]);
    }

    public function testRejectsEmptyMethodList(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one method');

        $this->runtime->expose('empty_methods', []);
    }

    public function testRejectsNonCallable(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->runtime->expose('bad_callable', ['a' => 'not_a_function_xyz']);
    }

    public function testRejectsNumericMethodName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->runtime->expose('bad_name', [0 => fn() => 1]);
    }

    public function testRejectsDuplicateAssetName(): void
    {
        $this->runtime->expose('dup_asset', ['a' => fn() => 1]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("'dup_asset' is already registered");

        $this->runtime->expose('dup_asset', ['b' => fn() => 2]);
    }
}
