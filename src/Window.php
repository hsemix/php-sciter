<?php

namespace Sciter;

use FFI;
use FFI\CData;
use RuntimeException;

final class Window
{
    private const SW_MAIN = 1 << 7;

    private const WINDOW_SET_STATE = 1;
    private const WINDOW_STATE_SHOWN = 2;

    private ?CData $handle = null;

    public function __construct(
        private Runtime $runtime,
        private int $width = 900,
        private int $height = 600,
        private int $x = 100,
        private int $y = 100,
    ) {}

    public function create(): self
    {
        if ($this->handle !== null) {
            return $this;
        }

        $this->runtime->initialize();

        $ffi = $this->runtime->ffi();
        $api = $this->runtime->api();

        $frame = $ffi->new('RECT');

        $frame->left = $this->x;
        $frame->top = $this->y;
        $frame->right = $this->x + $this->width;
        $frame->bottom = $this->y + $this->height;

        $create = $api->SciterCreateWindow;

        $handle = $create(
            self::SW_MAIN,
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
}
