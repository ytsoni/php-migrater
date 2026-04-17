<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Analyzer\Visitors;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use Ylab\PhpMigrater\Analyzer\Issue;
use Ylab\PhpMigrater\Analyzer\IssueCategory;
use Ylab\PhpMigrater\Analyzer\Severity;
use Ylab\PhpMigrater\Config\PhpVersion;
use Ylab\PhpMigrater\Fixer\Fixers\ResourceToObjectFixer;

/**
 * Detects is_resource() calls on types that migrated from resources to objects.
 *
 * PHP 8.0+: CurlHandle, CurlMultiHandle, CurlShareHandle, GdImage, etc.
 * PHP 8.1+: FTPConnection, IMAP\Connection, LDAP\Connection, PgSql\Connection, etc.
 */
final class IsResourceVisitor extends NodeVisitorAbstract
{
    /** @var Issue[] */
    private array $issues = [];
    private string $filePath = '';

    /** Resources migrated to objects by PHP version */
    private const MIGRATED_RESOURCES = [
        '8.0' => ['curl', 'curl_multi', 'curl_share', 'gd', 'openssl', 'shmop', 'socket', 'sysvmsg', 'sysvsem', 'sysvshm', 'xml'],
        '8.1' => ['ftp', 'imap', 'ldap', 'pgsql', 'pspell'],
    ];

    public function setFilePath(string $filePath): void
    {
        $this->filePath = $filePath;
        $this->issues = [];
    }

    /** @return Issue[] */
    public function getIssues(): array
    {
        return $this->issues;
    }

    public function enterNode(Node $node): ?int
    {
        if (!$node instanceof Node\Expr\FuncCall) {
            return null;
        }

        if (!$node->name instanceof Node\Name) {
            return null;
        }

        $funcName = strtolower($node->name->toString());

        if ($funcName === 'is_resource') {
            $this->issues[] = new Issue(
                file: $this->filePath,
                line: $node->getStartLine(),
                column: 0,
                severity: Severity::Warning,
                message: 'is_resource() may return false for handles migrated to objects in PHP 8.0+. Consider instanceof checks.',
                category: IssueCategory::ResourceToObject,
                affectedFrom: PhpVersion::PHP_80,
                suggestedFixerClass: ResourceToObjectFixer::class,
            );
        }

        return null;
    }
}
