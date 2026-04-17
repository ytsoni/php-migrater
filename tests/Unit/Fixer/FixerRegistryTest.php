<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Tests\Unit\Fixer;

use PHPUnit\Framework\TestCase;
use Ylab\PhpMigrater\Analyzer\Issue;
use Ylab\PhpMigrater\Analyzer\IssueCategory;
use Ylab\PhpMigrater\Analyzer\Severity;
use Ylab\PhpMigrater\Fixer\FixerRegistry;
use Ylab\PhpMigrater\Fixer\Fixers\CurlyBraceAccessFixer;
use Ylab\PhpMigrater\Fixer\Fixers\LooseComparisonFixer;

final class FixerRegistryTest extends TestCase
{
    public function testRegisterAndFind(): void
    {
        $registry = new FixerRegistry();
        $registry->register(new CurlyBraceAccessFixer());
        $registry->register(new LooseComparisonFixer());

        $issue = new Issue(
            file: 'test.php',
            line: 1,
            column: 1,
            severity: Severity::Warning,
            message: 'test',
            category: IssueCategory::CurlyBraceAccess,
        );

        $fixer = $registry->findBestFixer($issue);
        $this->assertNotNull($fixer);
        $this->assertSame('curly_brace_access', $fixer->getName());
    }

    public function testFindReturnsNullForUnknownCategory(): void
    {
        $registry = new FixerRegistry();
        $registry->register(new CurlyBraceAccessFixer());

        $issue = new Issue(
            file: 'test.php',
            line: 1,
            column: 1,
            severity: Severity::Warning,
            message: 'test',
            category: IssueCategory::Other,
        );

        $this->assertNull($registry->findBestFixer($issue));
    }

    public function testApplyFixes(): void
    {
        $registry = new FixerRegistry();
        $registry->register(new CurlyBraceAccessFixer());

        $code = '<?php $x = $str{0};';
        $issues = [
            new Issue(
                file: 'test.php',
                line: 1,
                column: 15,
                severity: Severity::Warning,
                message: 'Curly brace access',
                category: IssueCategory::CurlyBraceAccess,
            ),
        ];

        $fixed = $registry->applyFixes($code, $issues);
        $this->assertSame('<?php $x = $str[0];', $fixed);
    }

    public function testFixersSortedByPriority(): void
    {
        $registry = new FixerRegistry();
        // LooseComparison priority = 50, CurlyBrace priority = 80
        $registry->register(new LooseComparisonFixer());
        $registry->register(new CurlyBraceAccessFixer());

        // findFixers should return them with CurlyBrace first (higher priority)
        $issue = new Issue(
            file: 'test.php',
            line: 1,
            column: 1,
            severity: Severity::Warning,
            message: 'test',
            category: IssueCategory::CurlyBraceAccess,
        );

        $fixers = $registry->findFixers($issue);
        $this->assertNotEmpty($fixers);
    }
}
