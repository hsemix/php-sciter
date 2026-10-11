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


    private const TYPE_BOOL = 2;
    private const TYPE_INT = 3;
    private const TYPE_FLOAT = 4;
    private const TYPE_STRING = 5;

    private const TYPE_UNDEFINED = 0;
    private const TYPE_NULL = 1;

    private const TYPE_ARRAY = 9;

    private const TYPE_OBJECT = 13;

    private const OBJECT_ARRAY = 0;
    private const OBJECT_PLAIN = 1;

    private const TYPE_MAP = 10;

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

    /**
     * Read an integer from a Sciter VALUE.
     */
    public function toInt(CData $valuePtr): int
    {
        $ffi = $this->runtime->ffi();
        $api = $this->runtime->api();

        $number = $ffi->new('int32_t[1]');

        $status = ($api->ValueIntData)(
            $valuePtr,
            $number
        );

        if ($status !== self::OK) {
            throw new RuntimeException(
                "ValueIntData() failed with code {$status}."
            );
        }

        return $number[0];
    }

    /**
     * Store a PHP integer in a Sciter VALUE.
     *
     * The destination must already be initialized.
     */
    public function setInt(
        CData $valuePtr,
        int $number
    ): void {
        if ($number < -2147483648 || $number > 2147483647) {
            throw new \OutOfRangeException(
                'Sciter integer value exceeds the signed 32-bit range.'
            );
        }

        $status = ($this->runtime->api()->ValueIntDataSet)(
            $valuePtr,
            $number,
            3, // T_INT
            0  // Units
        );

        if ($status !== self::OK) {
            throw new RuntimeException(
                "ValueIntDataSet() failed with code {$status}."
            );
        }
    }

    /**
     * Read a boolean from Sciter.
     */
    public function toBool(CData $valuePtr): bool
    {
        $ffi = $this->runtime->ffi();

        $data = $ffi->new('int32_t[1]');

        $status = ($this->runtime->api()->ValueIntData)(
            $valuePtr,
            $data
        );

        if ($status !== self::OK) {
            throw new RuntimeException(
                "ValueIntData() failed with code {$status}."
            );
        }

        return $data[0] !== 0;
    }

    /**
     * Write a PHP boolean into a Sciter VALUE.
     */
    public function setBool(CData $valuePtr, bool $value): void
    {
        $status = ($this->runtime->api()->ValueIntDataSet)(
            $valuePtr,
            $value ? 1 : 0,
            2, // T_BOOL
            0
        );

        if ($status !== self::OK) {
            throw new RuntimeException(
                "ValueIntDataSet() failed with code {$status}."
            );
        }
    }

    /**
     * Read a floating-point value from Sciter.
     */
    public function toFloat(CData $valuePtr): float
    {
        fwrite(
            STDERR,
            sprintf(
                "[Value] type=%d, units=%d\n",
                $valuePtr[0]->t,
                $valuePtr[0]->u
            )
        );

        $type = (int) $valuePtr[0]->t;

        if ($type === self::TYPE_INT) {
            return (float) $this->toInt($valuePtr);
        }

        if ($type !== self::TYPE_FLOAT) {
            throw new \InvalidArgumentException(
                "Cannot convert Sciter VALUE type {$type} to float."
            );
        }

        $data = $this->runtime->ffi()->new('FLOAT_VALUE[1]');

        $status = ($this->runtime->api()->ValueFloatData)(
            $valuePtr,
            $data
        );

        if ($status !== self::OK) {
            throw new RuntimeException(
                "ValueFloatData() failed with code {$status}."
            );
        }

        return (float) $data[0];
    }

    /**
     * Write a PHP float into a Sciter VALUE.
     */
    public function setFloat(CData $valuePtr, float $value): void
    {
        $status = ($this->runtime->api()->ValueFloatDataSet)(
            $valuePtr,
            $value,
            4, // T_FLOAT
            0
        );

        if ($status !== self::OK) {
            throw new RuntimeException(
                "ValueFloatDataSet() failed with code {$status}."
            );
        }
    }

    public function isNull(CData $valuePtr): bool
    {
        return (int) $valuePtr[0]->t === self::TYPE_NULL;
    }

    public function isUndefined(CData $valuePtr): bool
    {
        return (int) $valuePtr[0]->t === self::TYPE_UNDEFINED;
    }

    public function setNull(CData $valuePtr): void
    {
        $api = $this->runtime->api();

        // Initialize the VALUE using Sciter's API.
        $status = ($api->ValueInit)($valuePtr);

        if ($status !== self::OK) {
            throw new \RuntimeException(
                "ValueInit() failed: {$status}"
            );
        }

        // Represent a Sciter null value.
        $valuePtr[0]->t = self::TYPE_NULL;
        $valuePtr[0]->u = 0;
        $valuePtr[0]->d = 0;
    }

    public function toArray(CData $valuePtr): array
    {
        $ffi = $this->runtime->ffi();
        $api = $this->runtime->api();

        $count = $ffi->new('int32_t[1]');

        $status = ($api->ValueElementsCount)(
            $valuePtr,
            $count
        );

        if ($status !== self::OK) {
            throw new \RuntimeException(
                "ValueElementsCount() failed: {$status}"
            );
        }

        $result = [];

        for ($i = 0; $i < $count[0]; $i++) {
            $element = $ffi->new('VALUE');

            ($api->ValueInit)(FFI::addr($element));

            try {
                $status = ($api->ValueNthElementValue)(
                    $valuePtr,
                    $i,
                    FFI::addr($element)
                );

                if ($status !== self::OK) {
                    throw new \RuntimeException(
                        "ValueNthElementValue() failed at index {$i}: {$status}"
                    );
                }

                $result[] = $this->toPhp(
                    FFI::addr($element)
                );
            } finally {
                ($api->ValueClear)(FFI::addr($element));
            }
        }

        return $result;
    }

    public function setArray(CData $valuePtr, array $values): void
    {
        $ffi = $this->runtime->ffi();
        $api = $this->runtime->api();

        // Only indexed arrays for now.
        if (!array_is_list($values)) {
            throw new \InvalidArgumentException(
                'Associative arrays are not supported yet.'
            );
        }

        // Destination is fresh SOM result storage.
        $status = ($api->ValueInit)($valuePtr);

        if ($status !== self::OK) {
            throw new \RuntimeException(
                "ValueInit() failed: {$status}"
            );
        }

        // An empty VALUE can be populated using the element setter.
        foreach ($values as $index => $item) {
            $element = $ffi->new('VALUE');

            ($api->ValueInit)(FFI::addr($element));

            try {
                $this->fromPhp(
                    FFI::addr($element),
                    $item
                );

                $status = ($api->ValueNthElementValueSet)(
                    $valuePtr,
                    $index,
                    FFI::addr($element)
                );

                if ($status !== self::OK) {
                    throw new \RuntimeException(
                        "ValueNthElementValueSet() failed at index {$index}: {$status}"
                    );
                }
            } finally {
                ($api->ValueClear)(FFI::addr($element));
            }
        }
    }

    public function toObject(CData $valuePtr): mixed
    {
        $ffi = $this->runtime->ffi();
        $api = $this->runtime->api();

        $type = (int) $valuePtr[0]->t;
        $unit = (int) $valuePtr[0]->u;

        if ($type !== self::TYPE_OBJECT) {
            throw new \InvalidArgumentException(
                'Expected a Sciter object.'
            );
        }

        if (!in_array($unit, [
            self::OBJECT_ARRAY,
            self::OBJECT_PLAIN,
        ], true)) {
            throw new \InvalidArgumentException(
                "Unsupported Sciter object subtype: {$unit}"
            );
        }

        $copy = $ffi->new('VALUE');
        $copyPtr = FFI::addr($copy);

        $status = ($api->ValueInit)($copyPtr);

        if ($status !== self::OK) {
            throw new \RuntimeException(
                "ValueInit() failed: {$status}"
            );
        }

        try {
            $status = ($api->ValueCopy)(
                $copyPtr,
                $valuePtr
            );

            if ($status !== self::OK) {
                throw new \RuntimeException(
                    "ValueCopy() failed: {$status}"
                );
            }

            $status = ($api->ValueIsolate)($copyPtr);

            if ($status !== self::OK) {
                throw new \RuntimeException(
                    "ValueIsolate() failed: {$status}"
                );
            }

            // The isolated value should now be T_ARRAY or T_MAP.
            return $this->toPhp($copyPtr);
        } finally {
            ($api->ValueClear)($copyPtr);
        }
    }

    public function setMap(CData $valuePtr, array $values): void
    {
        $ffi = $this->runtime->ffi();
        $api = $this->runtime->api();

        $status = ($api->ValueInit)($valuePtr);

        if ($status !== self::OK) {
            throw new RuntimeException(
                "ValueInit() failed: {$status}"
            );
        }

        foreach ($values as $key => $value) {
            $keyValue = $ffi->new('VALUE');
            $itemValue = $ffi->new('VALUE');

            $keyPtr = FFI::addr($keyValue);
            $itemPtr = FFI::addr($itemValue);

            ($api->ValueInit)($keyPtr);
            ($api->ValueInit)($itemPtr);

            try {
                $this->setString($keyPtr, (string) $key);
                // $this->set($itemPtr, $value);
                $this->fromPhp($itemPtr, $value);

                $status = ($api->ValueSetValueToKey)(
                    $valuePtr,
                    $keyPtr,
                    $itemPtr
                );

                if ($status !== self::OK) {
                    throw new RuntimeException(
                        "ValueSetValueToKey() failed: {$status}"
                    );
                }
            } finally {
                ($api->ValueClear)($itemPtr);
                ($api->ValueClear)($keyPtr);
            }
        }
    }

    public function set(CData $valuePtr, mixed $value): void
    {
        if ($value === null) {
            $this->setNull($valuePtr);
            return;
        }

        if (is_bool($value)) {
            $this->setBool($valuePtr, $value);
            return;
        }

        if (is_int($value)) {
            $this->setInt($valuePtr, $value);
            return;
        }

        if (is_float($value)) {
            $this->setFloat($valuePtr, $value);
            return;
        }

        if (is_string($value)) {
            $this->setString($valuePtr, $value);
            return;
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                $this->setArray($valuePtr, $value);
            } else {
                $this->setMap($valuePtr, $value);
            }

            return;
        }

        throw new \InvalidArgumentException(
            'Unsupported PHP value type: ' . get_debug_type($value)
        );
    }

    /**
     * Convert a Sciter VALUE into a native PHP scalar.
     */
    public function toPhp(CData $valuePtr): mixed
    {
        $type = (int) $valuePtr[0]->t;

        return match ($type) {
            self::TYPE_UNDEFINED,
            self::TYPE_NULL => null,

            self::TYPE_BOOL => $this->toBool($valuePtr),
            self::TYPE_INT => $this->toInt($valuePtr),
            self::TYPE_FLOAT => $this->toFloat($valuePtr),
            self::TYPE_STRING => $this->toString($valuePtr),

            self::TYPE_ARRAY => $this->toArray($valuePtr),

            self::TYPE_OBJECT => $this->toObject($valuePtr),

            default => throw new \InvalidArgumentException(
                "Unsupported Sciter VALUE type: {$type}"
            ),
        };
    }

    /**
     * Store a PHP scalar into an initialized Sciter VALUE.
     */
    public function fromPhp(
        CData $valuePtr,
        mixed $value
    ): void {
        match (true) {
            $value === null => $this->setNull($valuePtr),
            is_string($value) => $this->setString($valuePtr, $value),
            is_int($value) => $this->setInt($valuePtr, $value),
            is_float($value) => $this->setFloat($valuePtr, $value),
            is_bool($value) => $this->setBool($valuePtr, $value),
            is_array($value) => $this->setArray($valuePtr, $value),
        };
    }
}
