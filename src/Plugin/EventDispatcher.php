<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Plugin;

final class EventDispatcher
{
    /** @var array<string, list<callable(Event): void>> */
    private array $listeners = [];

    public function listen(EventType $type, callable $listener): void
    {
        $this->listeners[$type->value][] = $listener;
    }

    public function dispatch(Event $event): void
    {
        $listeners = $this->listeners[$event->type->value] ?? [];
        foreach ($listeners as $listener) {
            $listener($event);
        }
    }
}
