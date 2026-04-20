<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\TestGenerator;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\Node\Stmt;

/**
 * Extracts functions and methods from source code for test generation.
 */
final class FunctionExtractor
{
    /**
     * @return array<ExtractedFunction>
     */
    public function extract(string $sourceCode): array
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        try {
            $stmts = $parser->parse($sourceCode);
        } catch (\Throwable) {
            return [];
        }

        if ($stmts === null) {
            return [];
        }

        $traverser = new NodeTraverser();
        $visitor = new class extends NodeVisitorAbstract {
            /** @var array<ExtractedFunction> */
            public array $functions = [];
            private ?string $currentClass = null;
            private ?string $currentNamespace = null;

            public function enterNode(Node $node): ?int
            {
                if ($node instanceof Stmt\Namespace_) {
                    $this->currentNamespace = $node->name?->toString();
                    return null;
                }

                if ($node instanceof Stmt\Class_ || $node instanceof Stmt\Trait_) {
                    $this->currentClass = $node->name?->name;
                    return null;
                }

                if ($node instanceof Stmt\Function_) {
                    $this->functions[] = new ExtractedFunction(
                        name: $node->name->name,
                        className: null,
                        namespace: $this->currentNamespace,
                        params: $this->extractParams($node->params),
                        returnType: $this->nodeTypeToString($node->returnType),
                        isStatic: false,
                        visibility: 'public',
                        startLine: $node->getStartLine(),
                        endLine: $node->getEndLine(),
                    );
                    return NodeVisitor::DONT_TRAVERSE_CHILDREN;
                }

                if ($node instanceof Stmt\ClassMethod) {
                    $visibility = 'public';
                    if ($node->isProtected()) {
                        $visibility = 'protected';
                    } elseif ($node->isPrivate()) {
                        $visibility = 'private';
                    }

                    $this->functions[] = new ExtractedFunction(
                        name: $node->name->name,
                        className: $this->currentClass,
                        namespace: $this->currentNamespace,
                        params: $this->extractParams($node->params),
                        returnType: $this->nodeTypeToString($node->returnType),
                        isStatic: $node->isStatic(),
                        visibility: $visibility,
                        startLine: $node->getStartLine(),
                        endLine: $node->getEndLine(),
                    );
                    return NodeVisitor::DONT_TRAVERSE_CHILDREN;
                }

                return null;
            }

            public function leaveNode(Node $node): ?int
            {
                if ($node instanceof Stmt\Class_ || $node instanceof Stmt\Trait_) {
                    $this->currentClass = null;
                }
                return null;
            }

            /**
             * @param array<Node\Param> $params
             * @return array<array{name: string, type: ?string, default: bool}>
             */
            private function extractParams(array $params): array
            {
                $result = [];
                foreach ($params as $param) {
                    $result[] = [
                        'name' => '$' . ($param->var instanceof Node\Expr\Variable && is_string($param->var->name) ? $param->var->name : 'unknown'),
                        'type' => $this->nodeTypeToString($param->type),
                        'default' => $param->default !== null,
                    ];
                }
                return $result;
            }

            private function nodeTypeToString(?Node $type): ?string
            {
                if ($type === null) {
                    return null;
                }
                if ($type instanceof Node\Identifier) {
                    return $type->name;
                }
                if ($type instanceof Node\Name) {
                    return $type->toString();
                }
                if ($type instanceof Node\NullableType) {
                    return '?' . $this->nodeTypeToString($type->type);
                }
                if ($type instanceof Node\UnionType) {
                    return implode('|', array_map(
                        fn($t) => $this->nodeTypeToString($t) ?? 'mixed',
                        $type->types,
                    ));
                }
                return null;
            }
        };

        $traverser->addVisitor($visitor);
        try {
            $traverser->traverse($stmts);
        } catch (\Throwable) {
            // Return whatever functions were extracted before the error
        }

        return $visitor->functions;
    }
}
