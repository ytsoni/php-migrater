<?php
// Fixture: Simple functions for FunctionExtractor tests

namespace App\Utils;

function calculateTotal(float $price, int $quantity = 1): float {
    return $price * $quantity;
}

function formatName(string $first, ?string $last = null): string {
    return $last !== null ? "$first $last" : $first;
}

class Calculator {
    private float $result = 0;

    public function add(float $value): self {
        $this->result += $value;
        return $this;
    }

    public static function create(): self {
        return new self();
    }

    protected function reset(): void {
        $this->result = 0;
    }

    private function log(string $message): void {
        // noop
    }

    public function getResult(): float {
        return $this->result;
    }
}
