<?php

declare(strict_types=1);

namespace Sciter\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Sciter\Runtime;
use Throwable;

/**
 * Base class for tests that load the real Sciter library.
 *
 * They are skipped when FFI or the bundled binary is unavailable.
 */
abstract class IntegrationTestCase extends TestCase
{
    private static ?Runtime $shared = null;

    protected static function runtime(): Runtime
    {
        if (self::$shared !== null) {
            return self::$shared;
        }

        if (!extension_loaded('ffi')) {
            self::markTestSkipped('The FFI extension is not loaded.');
        }

        try {
            $runtime = new Runtime();
            $runtime->initialize();
        } catch (Throwable $e) {
            self::markTestSkipped('Sciter is unavailable: ' . $e->getMessage());
        }

        return self::$shared = $runtime;
    }
}
