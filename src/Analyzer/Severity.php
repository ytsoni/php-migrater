<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Analyzer;

enum Severity: string
{
    case Error = 'error';
    case Warning = 'warning';
    case Info = 'info';

    public function weight(): int
    {
        return match ($this) {
            self::Error => 10,
            self::Warning => 3,
            self::Info => 1,
        };
    }
}
