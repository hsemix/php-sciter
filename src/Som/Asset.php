<?php

namespace Sciter\Som;

use Closure;
use FFI;
use FFI\CData;
use ReflectionFunction;
use Sciter\Runtime;

final class Asset
{
    private int $atom;

    private CData $asset;

    private CData $assetClass;

    private CData $passport;

    /*
     * Keep PHP references to FFI callbacks alive for as long
     * as this Asset exists.
     */
    private mixed $addRefCallback;

    private mixed $releaseCallback;

    private mixed $getInterfaceCallback;

    private mixed $getPassportCallback;

    private array $methodDefinitions = [];

    private array $methodCallbacks = [];

    private ?CData $methods = null;

    private bool $registered = false;

    /**
     * Retain globally registered assets while Sciter may use them.
     *
     * This is a temporary lifetime-management strategy.
     */
    private static array $registeredAssets = [];

    private int $references = 1;

    private MethodDispatcher $dispatcher;


    public function __construct(
        private Runtime $runtime,
        private string $name
    ) {
        $this->atom = $this->resolveAtom($name);
        $this->dispatcher = new MethodDispatcher($runtime);

        $this->createPassport();
        $this->createAssetClass();
        $this->createAsset();
    }

    public function name(): string
    {
        return $this->name;
    }

    public function atom(): int
    {
        return $this->atom;
    }

    /**
     * Underlying som_asset_t.
     *
     * This is internal infrastructure for now. We expose it
     * temporarily so that we can verify the structure.
     */
    public function cdata(): CData
    {
        return $this->asset;
    }

    private function resolveAtom(string $name): int
    {
        $api = $this->runtime->api();

        return ($api->SciterAtomValue)($name);
    }

    private function createAssetClass(): void
    {
        $ffi = $this->runtime->ffi();

        $this->addRefCallback = function ($thing): int {
            $this->references++;

            fwrite(
                STDERR,
                "[SOM] add_ref => {$this->references}\n"
            );

            return $this->references;
        };

        $this->releaseCallback = function ($thing): int {
            if ($this->references <= 0) {
                fwrite(STDERR, "[SOM] Unexpected release at zero references\n");
                return 0;
            }

            $this->references--;

            fwrite(
                STDERR,
                "[SOM] release => {$this->references}\n"
            );

            return $this->references;
        };

        $this->getInterfaceCallback = static function (
            $thing,
            $name,
            $out
        ): int {
            fwrite(STDERR, "[SOM] get_interface\n");

            return 0;
        };

        $this->getPassportCallback = function ($thing) {
            fwrite(STDERR, "[SOM] get_passport\n");

            return FFI::addr($this->passport);
        };

        $this->assetClass = $ffi->new('som_asset_class_t', false);

        $this->assetClass->asset_add_ref =
            $this->addRefCallback;

        $this->assetClass->asset_release =
            $this->releaseCallback;

        $this->assetClass->asset_get_interface =
            $this->getInterfaceCallback;

        $this->assetClass->asset_get_passport =
            $this->getPassportCallback;
    }

    public function references(): int
    {
        return $this->references;
    }

    private function createAsset(): void
    {
        $ffi = $this->runtime->ffi();

        $this->asset = $ffi->new('som_asset_t', false);

        /*
         * som_asset_t::isa expects som_asset_class_t*.
         */
        $this->asset->isa = FFI::addr(
            $this->assetClass
        );
    }

    private function createPassport(): void
    {
        $ffi = $this->runtime->ffi();

        $this->passport = $ffi->new('som_passport_t', false);

        // Initialize the entire structure.
        FFI::memset(
            FFI::addr($this->passport),
            0,
            FFI::sizeof($this->passport)
        );

        $this->passport->flags = 0;
        $this->passport->name = $this->atom;

        $this->passport->n_properties = 0;
        $this->passport->n_methods = 0;
    }

    public function passport(): CData
    {
        return $this->passport;
    }

    public function method(
        string $name,
        callable $callback,
        ?int $params = null
    ): self {

        $reflection = new \ReflectionFunction(
            Closure::fromCallable($callback)
        );

        $params ??= $reflection->getNumberOfParameters();

        if ($this->registered) {
            throw new \LogicException(
                'Cannot add SOM methods after asset registration.'
            );
        }

        if ($name === '') {
            throw new \InvalidArgumentException(
                'SOM method name cannot be empty.'
            );
        }

        if ($params < 0) {
            throw new \InvalidArgumentException(
                'SOM method parameter count cannot be negative.'
            );
        }

        if (isset($this->methodDefinitions[$name])) {
            throw new \LogicException(
                "SOM method '{$name}' is already registered."
            );
        }

        $atom = $this->resolveAtom($name);

        $this->methodDefinitions[$name] = [
            'atom' => $atom,
            'params' => $params,
            'callback' => $callback,
        ];

        $this->rebuildMethods();

        return $this;
    }

    private function rebuildMethods(): void
    {
        $ffi = $this->runtime->ffi();

        $count = count($this->methodDefinitions);

        $methods = $ffi->new(
            'som_method_def_t[' . max(1, $count) . ']',
            false
        );

        $callbacks = [];

        $index = 0;

        foreach ($this->methodDefinitions as $definition) {
            $handler = $definition['callback'];

            $dispatcher = $this->dispatcher;

            $nativeCallback = static function (
                $thing,
                int $argc,
                $argv,
                $result
            ) use ($handler, $dispatcher): int {
                return $dispatcher->dispatch(
                    $handler,
                    $argc,
                    $argv,
                    $result
                ) ? 1 : 0;
            };

            $methods[$index]->reserved = null;
            $methods[$index]->name = $definition['atom'];
            $methods[$index]->params = $definition['params'];
            $methods[$index]->func = $nativeCallback;

            $callbacks[] = $nativeCallback;

            $index++;
        }

        /*
     * Retain the new definitions and callbacks.
     */
        $this->methods = $methods;
        $this->methodCallbacks = $callbacks;

        $this->passport->methods = $count > 0
            ? FFI::addr($this->methods[0])
            : null;

        $this->passport->n_methods = $count;
    }

    public function register(): self
    {
        if ($this->registered) {
            return $this;
        }

        $api = $this->runtime->api();

        $success = ($api->SciterSetGlobalAsset)(
            FFI::addr($this->asset)
        );

        if (!$success) {
            throw new \RuntimeException(
                "Failed to register SOM asset '{$this->name}'."
            );
        }

        $this->registered = true;

        // Keep the native structures and callbacks alive.
        self::$registeredAssets[spl_object_id($this)] = $this;

        return $this;
    }

    public function unregister(): void
    {
        if (!$this->registered) {
            return;
        }

        $success = ($this->runtime->api()->SciterReleaseGlobalAsset)(
            FFI::addr($this->asset)
        );

        if (!$success) {
            throw new \RuntimeException(
                "Failed to release SOM asset '{$this->name}'."
            );
        }

        $this->registered = false;

        /*
     * Deliberately keep the asset in our static registry for now.
     *
     * We don't yet know whether Sciter retains references to
     * the asset during JavaScript runtime shutdown.
     */
    }
}
