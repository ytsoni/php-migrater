<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Plugin;

final class Event
{
    public function __construct(
        public readonly EventType $type,
        /** @var array<string, mixed> */
        public readonly array $payload = [],
    ) {}
}


