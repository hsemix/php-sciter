<?php

namespace Sciter;

use FFI;
use FFI\CData;
use RuntimeException;

final class Window
{
    private const WINDOW_SET_STATE = 1;
    private const WINDOW_STATE_SHOWN = 2;

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

    public function show(): self
    {
        $this->create();

        $windowExec = $this->runtime
            ->api()
            ->SciterWindowExec;

        $windowExec(
            $this->handle,
            self::WINDOW_SET_STATE,
            self::WINDOW_STATE_SHOWN,
            0
        );

        return $this;
    }

    public function handle(): CData
    {
        $this->create();

        return $this->handle;
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
}
