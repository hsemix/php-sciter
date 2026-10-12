<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Sciter\FFI\Definitions;

final class AbiLayoutTest extends TestCase
{
    public function testSciterApiLayout(): void
    {
        $ffi = Definitions::load();

        $api = $ffi->new('ISciterAPI');

        self::assertSame(
            1520,
            \FFI::sizeof($api),
            'ISciterAPI structure size mismatch'
        );

        $expected = [
            'SciterSetCallback'       => 9,
            'SciterCreateWindow'      => 31,
            'ValueInit'               => 129,
            'ValueStringData'         => 135,
            'ValueStringDataSet'      => 136,
            'SciterGetCallbackParam'  => 165,
            'SciterPostCallback'      => 166,
            'SciterAtomValue'         => 173,
            'SciterSetGlobalAsset'    => 175,
        ];

        $pointerSize = \FFI::sizeof(
            $ffi->new('void*')
        );

        foreach ($expected as $field => $slot) {
            $offset = \FFI::typeof($api)->getStructFieldOffset($field);

            self::assertSame(
                $slot * $pointerSize,
                $offset,
                "{$field} has an incorrect ABI offset"
            );
        }
    }
}
