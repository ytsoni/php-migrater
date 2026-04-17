<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\TestGenerator;

final class ExtractedFunction
{
    /**
     * @param array<array{name: string, type: ?string, default: bool}> $params
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $className,
        public readonly ?string $namespace,
        public readonly array $params,
        public readonly ?string $returnType,
        public readonly bool $isStatic,
        public readonly string $visibility,
        public readonly int $startLine,
        public readonly int $endLine,
    ) {}

    public function isMethod(): bool
    {
        return $this->className !== null;
    }

    public function getFullClassName(): ?string
    {
        if ($this->className === null) {
            return null;
        }

        return $this->namespace !== null
            ? $this->namespace . '\\' . $this->className
            : $this->className;
    }
}
