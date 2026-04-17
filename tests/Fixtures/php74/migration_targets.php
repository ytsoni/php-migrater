<?php
// Fixture: PHP 7.4 style code with remaining issues for 8.x migration

// Nested ternary (deprecated 7.4, error 8.0)
// Note: Unparenthesized nested ternary is a parse error in PHP 8.0+
$result = $a ? $b : ($c ? $d : $e);

// Dynamic properties (deprecated 8.2)
class LegacyService {
    public function configure() {
        $this->timeout = 30;
        $this->retries = 3;
    }
}

// Implicit nullable (deprecated 8.4)
function processData(array $items, callable $filter = null): array {
    if ($filter !== null) {
        $items = array_filter($items, $filter);
    }
    return $items;
}

class DataProcessor {
    public function handle(string $input, ?int $limit = null): string {
        // Already correct - ?int
        return substr($input, 0, $limit ?? 100);
    }

    public function transform(array $data = null): array {
        // Implicit nullable - needs fix
        return $data ?? [];
    }
}
