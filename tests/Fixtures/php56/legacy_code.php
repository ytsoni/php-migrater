<?php
// Fixture: PHP 5.6 style code with known compatibility issues

$arr = array(1, 2, 3);

// Curly brace access (deprecated 7.4, removed 8.0)
// Note: {0} syntax cannot be parsed by php-parser v5
// CurlyBraceVisitor uses regex-based detection on source code
$str = "hello";
$first = $str[0];

// Loose comparison (behavior changed in 8.0)
if (0 == "foo") {
    echo "match";
}
if ("" == 0) {
    echo "empty match";
}

// Implicit nullable (deprecated 8.4)
function greet(string $name = null) {
    echo "Hello " . ($name ?? "world");
}

class OldClass {
    public $declared = 'yes';

    // Implicit nullable in method
    public function doSomething(array $data = null) {
        return $data ?? [];
    }

    // Dynamic property usage
    public function init() {
        $this->undeclared = 'dynamic';
    }
}

// is_resource usage (migrated in 8.0)
$ch = curl_init();
if (is_resource($ch)) {
    curl_close($ch);
}

// String to number comparison
$val = "abc";
if ($val == 0) {
    echo "was zero";
}
