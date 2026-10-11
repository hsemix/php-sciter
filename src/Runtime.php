<?php

namespace Sciter;

use FFI;
use FFI\CData;
use RuntimeException;
use Sciter\FFI\SciterApi;
use Sciter\Som\Asset;

final class Runtime
{
    private const APP_LOOP = 1;
    private const APP_INIT = 2;

    private const APP_STOP = 0;
    private const APP_SHUTDOWN = 3;

    private SciterApi $sciter;
    private bool $initialized = false;

    /**
     * SOM assets owned by this runtime.
     *
     * Retaining these objects also keeps their native structures
     * and PHP FFI callbacks alive.
     *
     * @var array<int, \Sciter\Som\Asset>
     */
    private array $assets = [];

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

    public function shutdown(): void
    {
        if (!$this->initialized) {
            return;
        }

        fwrite(STDERR, "[Runtime] Starting Sciter shutdown\n");

        /*
         * 1. Unregister SOM assets while Sciter is initialized.
         *
         * Do not destroy the PHP asset objects yet.
         */
        $this->releaseAssets();

        /*
         * 2. Shut down the Sciter runtime.
         */
        $result = ($this->api()->SciterExec)(
            self::APP_SHUTDOWN,
            0,
            0
        );

        fwrite(
            STDERR,
            "[Runtime] Sciter shutdown returned: {$result}\n"
        );

        /*
         * 3. Mark the runtime as shut down.
         */
        $this->initialized = false;

        /*
         * 4. Release PHP references only after native shutdown.
         */
        $this->assets = [];
    }

    public function retainAsset(Asset $asset): void
    {
        $this->assets[spl_object_id($asset)] = $asset;
    }

    /**
     * Release runtime ownership after Sciter has finished
     * using its registered SOM assets.
     */
    private function releaseAssets(): void
    {
        foreach ($this->assets as $asset) {
            $asset->unregister();
        }
    }
}
