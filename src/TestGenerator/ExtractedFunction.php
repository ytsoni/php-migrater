<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\TestGenerator;

final readonly class ExtractedFunction
{
    /**
     * @param array<array{name: string, type: ?string, default: bool}> $params
     */
    public function __construct(
        public string $name,
        public ?string $className,
        public ?string $namespace,
        public array $params,
        public ?string $returnType,
        public bool $isStatic,
        public string $visibility,
        public int $startLine,
        public int $endLine,
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
