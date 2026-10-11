<?php

declare(strict_types=1);

namespace Sciter;

final class WindowStateObserver
{
    private const CLOSED = 0;
    private const HIDDEN = 1;
    private const SHOWN = 2;
    private const MINIMIZED = 3;
    private const MAXIMIZED = 4;
    private const FULLSCREEN = 5;

    private ?int $previousState = null;

    public function __construct(
        private Window $window,
        private $onChange
    ) {
        if (!is_callable($onChange)) {
            throw new \InvalidArgumentException(
                'State change handler must be callable.'
            );
        }
    }

    public function initialize(): void
    {
        if ($this->window->isClosed() || $this->window->isClosing()) {
            return;
        }

        $this->previousState = $this->window->state();



        



        if ($this->window->isClosed() || $this->window->isClosing()) {
            return;
        }

        $this->previousState = $this->window->state();

        echo "[Observer] Initialized with state: {$this->previousState}\n";
    }

    public function check(): void
    {
        if ($this->window->isClosed() || $this->window->isClosing()) {
            return;
        }

        $currentState = $this->window->state();

        if ($this->previousState === null) {
            $this->previousState = $currentState;
            return;
        }

        if ($currentState === $this->previousState) {
            return;
        }

        $previous = $this->previousState;
        $this->previousState = $currentState;

        ($this->onChange)(
            $this->window,
            $previous,
            $currentState,
            $this->eventName($previous, $currentState)
        );
    }

    private function eventName(int $previous, int $current): ?string
    {
        return match (true) {
            $current === self::HIDDEN => 'hidden',

            $current === self::MINIMIZED => 'minimized',

            $current === self::MAXIMIZED => 'maximized',

            $current === self::FULLSCREEN => 'fullscreen',

            $current === self::SHOWN
                && in_array($previous, [
                    self::MINIMIZED,
                    self::MAXIMIZED,
                    self::FULLSCREEN,
                ], true) => 'restored',

            $current === self::SHOWN => 'shown',

            default => null,
        };
    }
}
