<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Reporter;

interface ReporterInterface
{
    public function getName(): string;

    /**
     * Generate a report from the migration report data.
     *
     * @return string The generated report content
     */
    public function render(MigrationReport $report): string;
}
