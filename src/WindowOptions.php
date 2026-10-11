<?php

declare(strict_types=1);

namespace Sciter;

final class WindowOptions
{
    public function __construct(
        public int $width = 900,
        public int $height = 600,
        public int $x = 100,
        public int $y = 100,
        public bool $main = true,
        public bool $resizable = true,
        public bool $titlebar = true,
        public bool $controls = true,
    ) {
        if ($width <= 0 || $height <= 0) {
            throw new \InvalidArgumentException(
                'Window width and height must be positive.'
            );
        }
    }
}
