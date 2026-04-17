<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Analyzer;

use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use SplFileInfo;
use Ylab\PhpMigrater\Analyzer\Visitors\CurlyBraceVisitor;
use Ylab\PhpMigrater\Analyzer\Visitors\DynamicPropertyVisitor;
use Ylab\PhpMigrater\Analyzer\Visitors\ImplicitNullableVisitor;
use Ylab\PhpMigrater\Analyzer\Visitors\IsResourceVisitor;
use Ylab\PhpMigrater\Analyzer\Visitors\LooseComparisonVisitor;
use Ylab\PhpMigrater\Analyzer\Visitors\StringToNumberVisitor;
use Ylab\PhpMigrater\Config\Configuration;

final class AstIssueDetector implements AnalyzerInterface
{
    private readonly LooseComparisonVisitor $looseComparison;
    private readonly IsResourceVisitor $isResource;
    private readonly CurlyBraceVisitor $curlyBrace;
    private readonly DynamicPropertyVisitor $dynamicProperty;
    private readonly ImplicitNullableVisitor $implicitNullable;
    private readonly StringToNumberVisitor $stringToNumber;

    public function __construct()
    {
        $this->looseComparison = new LooseComparisonVisitor();
        $this->isResource = new IsResourceVisitor();
        $this->curlyBrace = new CurlyBraceVisitor();
        $this->dynamicProperty = new DynamicPropertyVisitor();
        $this->implicitNullable = new ImplicitNullableVisitor();
        $this->stringToNumber = new StringToNumberVisitor();
    }

    public function getName(): string
    {
        return 'ast_issue_detector';
    }

    public function analyze(SplFileInfo $file, Configuration $config): array
    {
        $code = file_get_contents($file->getPathname());
        if ($code === false) {
            return [];
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        try {
            $ast = $parser->parse($code);
        } catch (\PhpParser\Error) {
            return [
                new Issue(
                    file: $file->getPathname(),
                    line: 0,
                    column: 0,
                    severity: Severity::Error,
                    message: 'Failed to parse PHP file (syntax error)',
                    category: IssueCategory::Syntax,
                ),
            ];
        }

        if ($ast === null) {
            return [];
        }

        $filePath = $file->getPathname();
        $visitors = $this->getVisitorsForConfig($config);

        foreach ($visitors as $visitor) {
            $visitor->setFilePath($filePath);
        }

        // CurlyBraceVisitor needs source code for detection
        $this->curlyBrace->setSourceCode($code);

        $traverser = new NodeTraverser();
        foreach ($visitors as $visitor) {
            $traverser->addVisitor($visitor);
        }
        $traverser->traverse($ast);

        $issues = [];
        foreach ($visitors as $visitor) {
            $issues = array_merge($issues, $visitor->getIssues());
        }

        return $issues;
    }

    /** @return list<LooseComparisonVisitor|IsResourceVisitor|CurlyBraceVisitor|DynamicPropertyVisitor|ImplicitNullableVisitor|StringToNumberVisitor> */
    private function getVisitorsForConfig(Configuration $config): array
    {
        $target = $config->getTargetVersion();
        $visitors = [];

        // Only include visitors relevant to the version migration
        if ($target->isNewerThanOrEqual(\Ylab\PhpMigrater\Config\PhpVersion::PHP_80)) {
            $visitors[] = $this->looseComparison;
            $visitors[] = $this->isResource;
            $visitors[] = $this->curlyBrace;
            $visitors[] = $this->stringToNumber;
        }

        if ($target->isNewerThanOrEqual(\Ylab\PhpMigrater\Config\PhpVersion::PHP_82)) {
            $visitors[] = $this->dynamicProperty;
        }

        if ($target->isNewerThanOrEqual(\Ylab\PhpMigrater\Config\PhpVersion::PHP_84)) {
            $visitors[] = $this->implicitNullable;
        }

        return $visitors;
    }
}
