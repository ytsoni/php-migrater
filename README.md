# PHP Migrater

A comprehensive PHP migration toolkit that analyzes, tests, and upgrades legacy PHP codebases (5.x → 8.x). Orchestrates custom AST-based analysis, PHPCompatibility, and Rector into a single workflow with interactive diff review, risk scoring, and incremental migration support.

## Features

- **AST-based analysis** — Detects loose comparisons, curly brace access, dynamic properties, implicit nullable types, nested ternaries, string-to-number coercions, and resource-to-object changes
- **Version detection** — Identifies PHP features used and maps to minimum required versions
- **PHPCompatibility integration** — Wraps `phpcs` with PHPCompatibility standard for broad compatibility scanning
- **Dependency analysis** — Checks `composer.lock` for packages with PHP version constraints
- **Risk scoring** — Ranks files by issue severity × code complexity for prioritized migration
- **7 built-in fixers** — Automated AST and regex-based fixes for common migration issues
- **Rector integration** — Optional orchestration of Rector upgrade rule sets
- **Interactive migration** — File-by-file diff review with apply/skip/quit, terminal or browser rendering
- **Resume support** — State file tracks progress; resume interrupted migrations
- **Test generation** — Characterization and behavioral test scaffolding before migration
- **Reporting** — Console, JSON, and HTML reports with severity/category breakdowns
- **Parallel analysis** — Worker pool for large codebases via `symfony/process`
- **Web dashboard** — Browser-based GUI for analysis and monitoring
- **Plugin architecture** — Extend with custom analyzers, fixers, test generators, and reporters

## Requirements

- PHP 8.1+
- Composer

## Installation

```bash
composer require ylab/php-migrater --dev
```

## Quick Start

### 1. Create a configuration file

```bash
cp vendor/ylab/php-migrater/php-migrater.php.dist php-migrater.php
```

Edit `php-migrater.php`:

```php
<?php
return [
    'source' => '5.6',
    'target' => '8.1',
    'paths' => ['src', 'lib'],
    'exclude' => ['vendor', 'tests'],
    'parallel' => 4,
];
```

### 2. Analyze your codebase

```bash
vendor/bin/php-migrater analyze
```

### 3. Generate safety tests

```bash
vendor/bin/php-migrater test:generate
```

### 4. Run the full migration

```bash
# Interactive mode (review each fix)
vendor/bin/php-migrater migrate

# Batch mode (apply all fixes)
vendor/bin/php-migrater migrate --batch

# With browser-based diff viewer
vendor/bin/php-migrater migrate --browser-diff
```

### 5. Generate a report

```bash
# Console
vendor/bin/php-migrater report

# HTML
vendor/bin/php-migrater report -f html report.html

# JSON
vendor/bin/php-migrater report -f json report.json
```

## Commands

| Command | Description |
|---------|-------------|
| `analyze` | Analyze codebase for migration issues |
| `fix` | Apply automated fixes (interactive or batch) |
| `migrate` | Full pipeline: analyze → generate tests → rector → fix → report |
| `report` | Generate migration report (console/json/html) |
| `test:generate` | Generate characterization tests for source files |
| `serve` | Start the web dashboard |

## Configuration Reference

```php
<?php
return [
    // Required: source and target PHP versions
    'source' => '5.6',
    'target' => '8.1',

    // Paths to analyze (relative to project root)
    'paths' => ['src'],

    // Directories to exclude
    'exclude' => ['vendor', 'tests', 'cache'],

    // Report output directory
    'report_output' => 'report',

    // Generated test output directory
    'test_output' => 'tests/migration',

    // Number of parallel workers (1 = sequential)
    'parallel' => 1,

    // Plugin classes to load
    'plugins' => [],

    // Web GUI port
    'web_port' => 8484,

    // State file for resume support
    'state_file' => '.php-migrater-state.json',
];
```

## Built-in Fixers

| Fixer | Priority | Description |
|-------|----------|-------------|
| CurlyBraceAccessFixer | 80 | `$str{0}` → `$str[0]` |
| NestedTernaryFixer | 70 | Adds parentheses to nested ternary expressions |
| ImplicitNullableFixer | 60 | `function f(Type $x = null)` → `function f(?Type $x = null)` |
| LooseComparisonFixer | 50 | `==` → `===` (AST-based) |
| StringToNumberFixer | 45 | Adds warning comments to implicit string-to-number coercions |
| ResourceToObjectFixer | 40 | `is_resource()` → `instanceof` checks |
| DynamicPropertyFixer | 30 | Adds `#[\AllowDynamicProperties]` attribute |

## Plugins

Create a plugin by implementing `Ylab\PhpMigrater\Plugin\PluginInterface`:

```php
use Ylab\PhpMigrater\Plugin\PluginInterface;

class MyPlugin implements PluginInterface
{
    public function getName(): string { return 'my-plugin'; }
    public function getAnalyzers(): array { return [new MyAnalyzer()]; }
    public function getFixers(): array { return []; }
    public function getTestGenerators(): array { return []; }
    public function getReporters(): array { return []; }
}
```

Register in config:

```php
'plugins' => [MyPlugin::class],
```

Or auto-discover via `composer.json`:

```json
{
    "extra": {
        "php-migrater": {
            "plugins": ["MyPlugin"]
        }
    }
}
```

## License

GNU General Public License v3.0 — see [LICENSE](LICENSE).
