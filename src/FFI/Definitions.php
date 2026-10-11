<?php

namespace Sciter\FFI;

use FFI;
use Sciter\Native\LibraryResolver;

final class Definitions
{

    public static function load(?string $libraryPath = null): FFI
    {
        $header = dirname(__DIR__, 2) . '/include/sciter-ffi.h';

        if (!is_readable($header)) {
            throw new \RuntimeException(
                "Sciter FFI header not found: {$header}"
            );
        }

        $definitions = file_get_contents($header);

        if ($definitions === false) {
            throw new \RuntimeException(
                "Unable to read Sciter FFI header: {$header}"
            );
        }

        return FFI::cdef(
            $definitions,
            LibraryResolver::resolve($libraryPath)
        );
    }
}
