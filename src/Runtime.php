<?php

namespace Sciter;

use FFI;
use FFI\CData;
use RuntimeException;
use Sciter\FFI\SciterApi;

final class Runtime
{
    private const APP_LOOP = 1;
    private const APP_INIT = 2;

    private const APP_STOP = 0;
    private const APP_SHUTDOWN = 3;

    private SciterApi $sciter;
    private bool $initialized = false;

    public function __construct(?string $library = null)
    {
        $library ??= $this->resolveLibrary();

        $this->sciter = new SciterApi($library);
    }

    public function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        $exec = $this->api()->SciterExec;

        $result = $exec(
            self::APP_INIT,
            0,
            0
        );

        if (!$result) {
            throw new RuntimeException(
                'Failed to initialize Sciter.'
            );
        }

        $this->initialized = true;
    }

    public function run(): int
    {
        $this->initialize();

        $exec = $this->api()->SciterExec;

        return $exec(
            self::APP_LOOP,
            0,
            0
        );
    }

    public function stop(int $exitCode = 0): void
    {
        if (!$this->initialized) {
            return;
        }

        ($this->api()->SciterExec)(
            self::APP_STOP,
            $exitCode,
            0
        );
    }

    public function ffi(): FFI
    {
        return $this->sciter->ffi();
    }

    public function api(): CData
    {
        return $this->sciter->api();
    }

    public function version(): int
    {
        return $this->sciter->version();
    }

    private function resolveLibrary(): string
    {
        $root = dirname(__DIR__);

        return match (PHP_OS_FAMILY) {
            'Darwin' => $root . '/bin/macos/libsciter.dylib',
            'Linux' => $root . '/bin/linux/libsciter.so',
            'Windows' => $root . '/bin/windows/sciter.dll',

            default => throw new RuntimeException(
                'Unsupported platform: ' . PHP_OS_FAMILY
            ),
        };
    }
}
