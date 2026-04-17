<?php
// E2E Fixture: Clean file with no issues

declare(strict_types=1);

final class CleanService
{
    public function __construct(
        private readonly string $name,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }
}
