<?php

namespace Sciter;

final class Application
{
    private Runtime $runtime;

    public function __construct(?Runtime $runtime = null)
    {
        $this->runtime = $runtime ?? new Runtime();
    }

    public function window(
        int $width = 900,
        int $height = 600,
        int $x = 100,
        int $y = 100,
    ): Window {
        return new Window(
            runtime: $this->runtime,
            width: $width,
            height: $height,
            x: $x,
            y: $y,
        );
    }

    public function run(Window $window): int
    {
        $window->create()->show();

        return $this->runtime->run();
    }

    public function runtime(): Runtime
    {
        return $this->runtime;
    }
}
