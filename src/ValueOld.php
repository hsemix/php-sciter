<?php

namespace Sciter;

use FFI;
use FFI\CData;
use RuntimeException;

final class ValueOld
{
    /*
     * VALUE_RESULT.HV_OK
     */
    private const OK = 0;

    /*
     * T_STRING units.
     *
     * UT_STRING_STRING = 0
     */
    private const STRING_UNIT = 0;

    public function __construct(
        private Runtime $runtime
    ) {}

    public function toString(CData $valuePtr): string
    {
        $ffi = $this->runtime->ffi();
        $api = $this->runtime->api();

        $chars = $ffi->new('WCHAR16*[1]');
        $length = $ffi->new('UINT[1]');

        $stringData = $api->ValueStringData;

        $result = $stringData(
            $valuePtr,
            $chars,
            $length
        );

        if ($result !== self::OK) {
            throw new RuntimeException(
                "ValueStringData() failed with code {$result}."
            );
        }

        $units = $length[0];

        echo "ValueStringData result: {$result}" . PHP_EOL;
        echo "UTF-16 length: {$units}" . PHP_EOL;

        if ($units === 0) {
            return '';
        }

        if (FFI::isNull($chars[0])) {
            throw new RuntimeException(
                'ValueStringData() returned NULL characters.'
            );
        }

        $bytes = FFI::string(
            FFI::cast('char*', $chars[0]),
            $units * 2
        );

        return mb_convert_encoding(
            $bytes,
            'UTF-8',
            'UTF-16LE'
        );
    }

    public function setString(
        CData $value,
        string $string
    ): void {
        $ffi = $this->runtime->ffi();
        $api = $this->runtime->api();

        /*
     * PHP UTF-8 -> Sciter UTF-16.
     */
        $utf16 = mb_convert_encoding(
            $string,
            'UTF-16LE',
            'UTF-8'
        );

        $units = intdiv(strlen($utf16), 2);

        /*
     * ValueStringDataSet() receives the explicit length,
     * so a terminator isn't required.
     */
        $buffer = $ffi->new(
            'WCHAR16[' . max(1, $units) . ']'
        );

        if ($units > 0) {
            FFI::memcpy(
                $buffer,
                $utf16,
                strlen($utf16)
            );
        }

        $set = $api->ValueStringDataSet;

        /*
        * IMPORTANT:
        *
        * $value is already VALUE* when setString() is called
        * with the SOM callback's $result parameter.
        *
        * Do NOT FFI::addr($value), because that produces VALUE**.
        */
        $result = $set(
            $value,
            $buffer,
            $units,
            self::STRING_UNIT
        );

        if ($result !== self::OK) {
            throw new RuntimeException(
                "ValueStringDataSet() failed with code {$result}."
            );
        }
    }
}
