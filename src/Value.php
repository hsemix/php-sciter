<?php

namespace Sciter;

use FFI;
use FFI\CData;
use RuntimeException;

final class Value
{
    /**
     * VALUE_RESULT::HV_OK
     */
    private const OK = 0;

    /**
     * VALUE_UNIT_TYPE_STRING::UT_STRING_STRING
     */
    private const STRING_UNIT = 0;

    public function __construct(
        private Runtime $runtime
    ) {}

    /**
     * Read a Sciter VALUE containing a string.
     *
     * $valuePtr must be VALUE* / const VALUE*.
     */
    public function toString(CData $valuePtr): string
    {
        $ffi = $this->runtime->ffi();
        $api = $this->runtime->api();

        $chars = $ffi->new('WCHAR16*[1]');
        $length = $ffi->new('UINT[1]');

        $result = ($api->ValueStringData)(
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

        if ($units === 0) {
            return '';
        }

        if (FFI::isNull($chars[0])) {
            throw new RuntimeException(
                'ValueStringData() returned NULL characters.'
            );
        }

        $bytes = FFI::string(
            $ffi->cast('char*', $chars[0]),
            $units * 2
        );

        return mb_convert_encoding(
            $bytes,
            'UTF-8',
            'UTF-16LE'
        );
    }

    /**
     * Store a PHP UTF-8 string into a Sciter VALUE.
     *
     * $valuePtr must already be VALUE*.
     */
    public function setString(
        CData $valuePtr,
        string $string
    ): void {
        $ffi = $this->runtime->ffi();
        $api = $this->runtime->api();

        /*
         * The SOM method result is storage supplied by Sciter.
         * Initialize it before assigning a VALUE to it.
         */
        $result = ($api->ValueInit)($valuePtr);

        if ($result !== self::OK) {
            throw new RuntimeException(
                "ValueInit() failed with code {$result}."
            );
        }

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
         * ValueStringDataSet() receives the character count,
         * therefore a null terminator is not required.
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

        /*
         * $valuePtr is already VALUE*.
         *
         * Do NOT use addr($valuePtr), which would turn it
         * into VALUE**.
         */
        $result = ($api->ValueStringDataSet)(
            $valuePtr,
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
