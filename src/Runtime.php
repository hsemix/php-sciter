<?php

namespace Sciter;

use FFI;
use FFI\CData;
use RuntimeException;
use Sciter\FFI\SciterApi;
use Sciter\Som\Asset;
use Sciter\Som\ExposedMethodResolver;

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
     * @var array<int, Asset>
     */
    private array $assets = [];

    /** @var array<string, Asset> */
    private array $namedAssets = [];

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

        $this->namedAssets = [];
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

    public function expose(string $name, array|object $methods): self
    {
        if ($name === '') {
            throw new \InvalidArgumentException(
                'Exposed asset name cannot be empty.'
            );
        }

        if (isset($this->namedAssets[$name])) {
            throw new \LogicException(
                "SOM asset '{$name}' is already registered."
            );
        }

        if (is_object($methods)) {
            $methods = (new ExposedMethodResolver())->resolve($methods);
        }

        if ($methods === []) {
            throw new \InvalidArgumentException(
                'At least one method must be exposed.'
            );
        }

        foreach ($methods as $method => $callback) {
            if (
                !is_string($method) ||
                $method === '' ||
                !is_callable($callback)
            ) {
                throw new \InvalidArgumentException(
                    'Exposed methods must have non-empty string names and callable values.'
                );
            }
        }

        $this->initialize();

        $asset = new Asset($this, $name);

        foreach ($methods as $method => $callback) {
            $asset->method($method, $callback);
        }

        $asset->register();

        // Asset::register() already calls retainAsset().
        $this->namedAssets[$name] = $asset;

        return $this;
    }
}
