<?php

declare(strict_types=1);

namespace Sciter\Events;

final class EventDispatcher
{
    /**
     * @var array<string, array<int, callable>>
     */
    private array $listeners = [];

    private int $nextListenerId = 0;

    public function on(string $event, callable $listener): int
    {
        if ($event === '') {
            throw new \InvalidArgumentException(
                'Event name cannot be empty.'
            );
        }

        $id = ++$this->nextListenerId;

        $this->listeners[$event][$id] = $listener;

        return $id;
    }

    public function off(string $event, int $listenerId): bool
    {
        if (!isset($this->listeners[$event][$listenerId])) {
            return false;
        }

        unset($this->listeners[$event][$listenerId]);

        if ($this->listeners[$event] === []) {
            unset($this->listeners[$event]);
        }

        return true;
    }

    public function emit(string $event, mixed ...$arguments): void
    {
        // Snapshot listeners so changes during dispatch
        // do not affect the current event.
        $listeners = $this->listeners[$event] ?? [];

        foreach ($listeners as $listener) {
            try {
                $listener(...$arguments);
            } catch (\Throwable $exception) {
                error_log(sprintf(
                    '[Sciter Event] Listener for "%s" failed: %s',
                    $event,
                    $exception->getMessage()
                ));
            }
        }
    }

    public function removeAllListeners(?string $event = null): void
    {
        if ($event === null) {
            $this->listeners = [];
            return;
        }

        unset($this->listeners[$event]);
    }

    public function listenerCount(string $event): int
    {
        return count($this->listeners[$event] ?? []);
    }

    public function once(string $event, callable $listener): int
    {
        $id = 0;

        $id = $this->on(
            $event,
            function (...$arguments) use ($event, $listener, &$id): void {
                $this->off($event, $id);

                $listener(...$arguments);
            }
        );

        return $id;
    }
}
