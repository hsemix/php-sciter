<?php

namespace Sciter;

use FFI;
use FFI\CData;
use RuntimeException;
use Sciter\Events\EventDispatcher;

final class Window
{
    private const WINDOW_SET_STATE = 1;
    private const WINDOW_STATE_SHOWN = 2;
    private const WINDOW_STATE_HIDDEN = 1;

    private const WINDOW_STATE_CLOSED = 0;
    private const WINDOW_STATE_MINIMIZED = 3;
    private const WINDOW_STATE_MAXIMIZED = 4;
    private const WINDOW_STATE_FULL_SCREEN = 5;
    private const WINDOW_GET_STATE = 2;

    private const SC_ENGINE_DESTROYED = 0x05;

    private mixed $hostCallback = null;

    private const SW_CHILD = 1 << 0;
    private const SW_TITLEBAR = 1 << 1;
    private const SW_RESIZEABLE = 1 << 2;
    private const SW_CONTROLS = 1 << 3;
    private const SW_MAIN = 1 << 7;

    private ?CData $handle = null;


    private const MAIN_WINDOW =
    self::SW_TITLEBAR |
        self::SW_RESIZEABLE |
        self::SW_CONTROLS |
        self::SW_MAIN;

    private const SECONDARY_WINDOW =
    self::SW_TITLEBAR |
        self::SW_RESIZEABLE |
        self::SW_CONTROLS;

    private WindowOptions $options;

    private bool $closed = false;

    private bool $closing = false;

    /** @var array<callable> */
    private array $closeCallbacks = [];

    private ?EventDispatcher $events = null;

    public function __construct(
        private Runtime $runtime,
        ?WindowOptions $options = null
    ) {
        $this->options = $options ?? new WindowOptions();
    }

    private function windowFlags(): int
    {
        $flags = 0;

        if ($this->options->titlebar) {
            $flags |= self::SW_TITLEBAR;
        }

        if ($this->options->resizable) {
            $flags |= self::SW_RESIZEABLE;
        }

        if ($this->options->controls) {
            $flags |= self::SW_CONTROLS;
        }

        if ($this->options->main) {
            $flags |= self::SW_MAIN;
        }

        return $flags;
    }

    public function create(): self
    {
        if ($this->closed) {
            throw new \LogicException(
                'Cannot create a window that has already been closed.'
            );
        }

        if ($this->handle !== null) {
            return $this;
        }

        $this->runtime->initialize();

        $ffi = $this->runtime->ffi();
        $api = $this->runtime->api();

        $frame = $ffi->new('RECT');

        $frame->left = $this->options->x;
        $frame->top = $this->options->y;

        $frame->right = $this->options->x + $this->options->width;
        $frame->bottom = $this->options->y + $this->options->height;

        $flags = $this->windowFlags();

        fwrite(
            STDERR,
            sprintf(
                "[Window] main=%s, resizable=%s, flags=%d (0x%X)\n",
                $this->options->main ? 'true' : 'false',
                $this->options->resizable ? 'true' : 'false',
                $flags,
                $flags
            )
        );

        $handle = ($api->SciterCreateWindow)(
            $flags,
            FFI::addr($frame),
            null,
            null,
            null
        );

        if (FFI::isNull($handle)) {
            throw new RuntimeException(
                'Failed to create Sciter window.'
            );
        }

        $this->handle = $handle;

        $this->hostCallback = function ($notification, $callbackParam): int {
            try {
                if ((int) $notification->code === self::SC_ENGINE_DESTROYED) {
                    if (!$this->closed) {
                        $this->closed = true;
                        $this->closing = false;
                        $this->handle = null;

                        $this->events()->emit('close', $this);
                    }
                }
            } catch (\Throwable $exception) {
                error_log(
                    '[Window] Native callback failed: '
                        . $exception->getMessage()
                );
            }

            return 0;
        };

        ($this->runtime->api()->SciterSetCallback)(
            $this->handle,
            $this->hostCallback,
            null
        );

        return $this;
    }

    public function loadHtml(string $html): self
    {
        $this->create();

        $ffi = $this->runtime->ffi();

        $length = strlen($html);

        if ($length === 0) {
            throw new RuntimeException(
                'Cannot load empty HTML.'
            );
        }

        $buffer = $ffi->new("BYTE[{$length}]");

        FFI::memcpy(
            $buffer,
            $html,
            $length
        );

        $load = $this->runtime
            ->api()
            ->SciterLoadHtml;

        $loaded = $load(
            $this->handle,
            $buffer,
            $length,
            null
        );

        if (!$loaded) {
            throw new RuntimeException(
                'SciterLoadHtml() failed.'
            );
        }

        return $this;
    }

    public function state(): int
    {
        if ($this->handle === null) {
            return self::WINDOW_STATE_CLOSED;
        }

        return (int) ($this->runtime->api()->SciterWindowExec)(
            $this->handle,
            self::WINDOW_GET_STATE,
            0,
            0
        );
    }

    private function setState(int $state): self
    {
        $this->create();

        ($this->runtime->api()->SciterWindowExec)(
            $this->handle,
            self::WINDOW_SET_STATE,
            $state,
            0
        );

        return $this;
    }

    public function fullscreen(): self
    {
        return $this->setState(self::WINDOW_STATE_FULL_SCREEN);
    }


    public function minimize(): void
    {
        $this->requestState(
            self::WINDOW_STATE_MINIMIZED,
            'minimize.requested'
        );
    }

    public function maximize(): void
    {
        $this->requestState(
            self::WINDOW_STATE_MAXIMIZED,
            'maximize.requested'
        );
    }

    public function restore(): void
    {
        $this->requestState(
            self::WINDOW_STATE_SHOWN,
            'restore.requested'
        );
    }

    public function hide(): void
    {
        $this->requestState(
            self::WINDOW_STATE_HIDDEN,
            'hide.requested'
        );
    }

    public function show(): void
    {
        $this->requestState(
            self::WINDOW_STATE_SHOWN,
            'show.requested'
        );
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    public function isClosing(): bool
    {
        return $this->closing;
    }

    public function close(): void
    {
        if ($this->closed || $this->closing) {
            return;
        }

        $this->closing = true;

        try {
            $this->setState(self::WINDOW_STATE_CLOSED);
        } catch (\Throwable $exception) {
            $this->closing = false;

            throw $exception;
        }
    }

    /**
     * Expose PHP callbacks to Sciter JavaScript.
     *
     * Example:
     *
     * $window->expose('app', [
     *     'greet' => fn (string $name) => "Hello, {$name}!",
     *     'add' => fn (int $a, int $b) => $a + $b,
     * ]);
     *
     * JavaScript:
     *
     * app.greet("Hamid");
     * app.add(10, 20);
     *
     * @param array<string, callable>|object $methods
     */
    public function expose(string $name, array|object $methods): self
    {
        $this->runtime->expose($name, $methods);

        return $this;
    }

    public function onClose(callable $callback): self
    {
        $this->on('close', $callback);

        return $this;
    }

    private function notifyClosed(): void
    {
        $callbacks = $this->closeCallbacks;
        $this->closeCallbacks = [];

        foreach ($callbacks as $callback) {
            try {
                $callback($this);
            } catch (\Throwable $exception) {
                error_log(
                    '[Window] Close callback failed: '
                        . $exception->getMessage()
                );
            }
        }
    }

    private function events(): EventDispatcher
    {
        return $this->events ??= new EventDispatcher();
    }

    public function on(string $event, callable $listener): int
    {
        if ($event === 'close' && $this->closed) {
            throw new \LogicException(
                'Cannot register a close listener on a closed window.'
            );
        }

        return $this->events()->on($event, $listener);
    }

    public function off(string $event, int $listenerId): bool
    {
        return $this->events()->off($event, $listenerId);
    }

    public function once(string $event, callable $listener): int
    {
        if ($event === 'close' && $this->closed) {
            throw new \LogicException(
                'Cannot register a close listener on a closed window.'
            );
        }

        return $this->events()->once($event, $listener);
    }

    private function requestState(int $state, string $event): void
    {
        if ($this->closed || $this->closing) {
            throw new \LogicException(
                'Cannot change the state of a closing or closed window.'
            );
        }

        $this->setState($state);

        $this->events()->emit($event, $this);
    }
}
