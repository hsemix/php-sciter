<?php

namespace Sciter\FFI;

use FFI;
use FFI\CData;
use RuntimeException;

final class SciterApi
{
    private FFI $ffi;

    private CData $api;

    public function __construct(string $library)
    {
        if (!extension_loaded('ffi')) {
            throw new RuntimeException(
                'PHP FFI extension is not enabled.'
            );
        }

        if (!is_file($library)) {
            throw new RuntimeException(
                "Sciter library not found: {$library}"
            );
        }

        $this->ffi = FFI::cdef(
            Definitions::cdef(),
            $library
        );

        $this->api = $this->ffi->SciterAPI();

        if (FFI::isNull($this->api)) {
            throw new RuntimeException(
                'SciterAPI() returned NULL.'
            );
        }
    }

    public function ffi(): FFI
    {
        return $this->ffi;
    }

    public function api(): CData
    {
        return $this->api;
    }

    public function version(): int
    {
        return $this->api->version;
    }
}
