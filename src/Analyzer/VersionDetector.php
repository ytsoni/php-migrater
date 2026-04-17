<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Analyzer;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use SplFileInfo;
use Ylab\PhpMigrater\Config\Configuration;
use Ylab\PhpMigrater\Config\PhpVersion;

/**
 * Detects the minimum PHP version required by analyzing language features used in code.
 */
final class VersionDetector implements AnalyzerInterface
{
    public function getName(): string
    {
        return 'version_detector';
    }

    public function analyze(SplFileInfo $file, Configuration $config): array
    {
        // VersionDetector doesn't produce Issues — it produces a VersionProfile.
        // Use detectVersion() directly for version detection.
        return [];
    }

    public function detectVersion(SplFileInfo $file): VersionProfile
    {
        $code = file_get_contents($file->getPathname());
        if ($code === false) {
            return new VersionProfile();
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        try {
            $ast = $parser->parse($code);
        } catch (\PhpParser\Error) {
            return new VersionProfile();
        }

        if ($ast === null) {
            return new VersionProfile();
        }

        $visitor = new VersionFeatureVisitor();
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->getProfile();
    }
}

/** @internal */
final class VersionFeatureVisitor extends NodeVisitorAbstract
{
    private VersionProfile $profile;

    public function __construct()
    {
        $this->profile = new VersionProfile();
    }

    public function getProfile(): VersionProfile
    {
        return $this->profile;
    }

    public function enterNode(Node $node): ?int
    {
        // PHP 5.4: Short array syntax
        if ($node instanceof Node\Expr\Array_ && $node->getAttribute('kind') === Node\Expr\Array_::KIND_SHORT) {
            $this->profile->addFeature('short_array_syntax', PhpVersion::PHP_54);
        }

        // PHP 5.4: Traits
        if ($node instanceof Node\Stmt\TraitUse) {
            $this->profile->addFeature('traits', PhpVersion::PHP_54);
        }

        // PHP 5.5: Generators (yield)
        if ($node instanceof Node\Expr\Yield_ || $node instanceof Node\Expr\YieldFrom) {
            $this->profile->addFeature('generators', PhpVersion::PHP_55);
        }

        // PHP 5.6: Variadic functions
        if ($node instanceof Node\Param && $node->variadic) {
            $this->profile->addFeature('variadic_params', PhpVersion::PHP_56);
        }

        // PHP 5.6: Argument unpacking
        if ($node instanceof Node\Arg && $node->unpack) {
            $this->profile->addFeature('argument_unpacking', PhpVersion::PHP_56);
        }

        // PHP 5.6: Constant expressions (const X = 1 + 2)
        if ($node instanceof Node\Stmt\Const_) {
            foreach ($node->consts as $const) {
                if (!$const->value instanceof Node\Scalar) {
                    $this->profile->addFeature('constant_expressions', PhpVersion::PHP_56);
                }
            }
        }

        // PHP 7.0: Return type declarations
        if ($node instanceof Node\FunctionLike && $node->getReturnType() !== null) {
            $this->profile->addFeature('return_types', PhpVersion::PHP_70);
        }

        // PHP 7.0: Null coalesce operator
        if ($node instanceof Node\Expr\BinaryOp\Coalesce) {
            $this->profile->addFeature('null_coalesce', PhpVersion::PHP_70);
        }

        // PHP 7.0: Spaceship operator
        if ($node instanceof Node\Expr\BinaryOp\Spaceship) {
            $this->profile->addFeature('spaceship_operator', PhpVersion::PHP_70);
        }

        // PHP 7.0: Scalar type hints
        if ($node instanceof Node\Param && $node->type instanceof Node\Identifier) {
            $typeName = strtolower($node->type->toString());
            if (in_array($typeName, ['int', 'float', 'string', 'bool'], true)) {
                $this->profile->addFeature('scalar_type_hints', PhpVersion::PHP_70);
            }
        }

        // PHP 7.1: Nullable types
        if ($node instanceof Node\NullableType) {
            $this->profile->addFeature('nullable_types', PhpVersion::PHP_71);
        }

        // PHP 7.1: Void return type
        if ($node instanceof Node\FunctionLike) {
            $returnType = $node->getReturnType();
            if ($returnType instanceof Node\Identifier && strtolower($returnType->toString()) === 'void') {
                $this->profile->addFeature('void_return_type', PhpVersion::PHP_71);
            }
        }

        // PHP 7.1: iterable type
        if ($node instanceof Node\Identifier && strtolower($node->toString()) === 'iterable') {
            $this->profile->addFeature('iterable_type', PhpVersion::PHP_71);
        }

        // PHP 7.4: Typed properties
        if ($node instanceof Node\Stmt\Property && $node->type !== null) {
            $this->profile->addFeature('typed_properties', PhpVersion::PHP_74);
        }

        // PHP 7.4: Arrow functions
        if ($node instanceof Node\Expr\ArrowFunction) {
            $this->profile->addFeature('arrow_functions', PhpVersion::PHP_74);
        }

        // PHP 7.4: Null coalescing assignment
        if ($node instanceof Node\Expr\AssignOp\Coalesce) {
            $this->profile->addFeature('null_coalesce_assign', PhpVersion::PHP_74);
        }

        // PHP 7.4: Numeric literal separator
        if ($node instanceof Node\Scalar\Int_ || $node instanceof Node\Scalar\Float_) {
            $raw = $node->getAttribute('rawValue', '');
            if (is_string($raw) && str_contains($raw, '_')) {
                $this->profile->addFeature('numeric_literal_separator', PhpVersion::PHP_74);
            }
        }

        // PHP 8.0: Named arguments
        if ($node instanceof Node\Arg && $node->name !== null) {
            $this->profile->addFeature('named_arguments', PhpVersion::PHP_80);
        }

        // PHP 8.0: Match expression
        if ($node instanceof Node\Expr\Match_) {
            $this->profile->addFeature('match_expression', PhpVersion::PHP_80);
        }

        // PHP 8.0: Nullsafe operator
        if ($node instanceof Node\Expr\NullsafePropertyFetch || $node instanceof Node\Expr\NullsafeMethodCall) {
            $this->profile->addFeature('nullsafe_operator', PhpVersion::PHP_80);
        }

        // PHP 8.0: Union types
        if ($node instanceof Node\UnionType) {
            $this->profile->addFeature('union_types', PhpVersion::PHP_80);
        }

        // PHP 8.0: Constructor property promotion
        if ($node instanceof Node\Param && $node->flags !== 0) {
            $this->profile->addFeature('constructor_promotion', PhpVersion::PHP_80);
        }

        // PHP 8.0: Attributes
        if ($node instanceof Node\AttributeGroup) {
            $this->profile->addFeature('attributes', PhpVersion::PHP_80);
        }

        // PHP 8.1: Enums
        if ($node instanceof Node\Stmt\Enum_) {
            $this->profile->addFeature('enums', PhpVersion::PHP_81);
        }

        // PHP 8.1: Readonly properties
        if ($node instanceof Node\Stmt\Property && ($node->flags & Node\Stmt\Class_::MODIFIER_READONLY)) {
            $this->profile->addFeature('readonly_properties', PhpVersion::PHP_81);
        }

        // PHP 8.1: Intersection types
        if ($node instanceof Node\IntersectionType) {
            $this->profile->addFeature('intersection_types', PhpVersion::PHP_81);
        }

        // PHP 8.1: Fibers
        if ($node instanceof Node\Expr\New_ && $node->class instanceof Node\Name) {
            if ($node->class->toString() === 'Fiber' || str_ends_with($node->class->toString(), '\\Fiber')) {
                $this->profile->addFeature('fibers', PhpVersion::PHP_81);
            }
        }

        // PHP 8.1: never return type
        if ($node instanceof Node\FunctionLike) {
            $returnType = $node->getReturnType();
            if ($returnType instanceof Node\Identifier && strtolower($returnType->toString()) === 'never') {
                $this->profile->addFeature('never_return_type', PhpVersion::PHP_81);
            }
        }

        // PHP 8.2: Readonly classes
        if ($node instanceof Node\Stmt\Class_ && ($node->flags & Node\Stmt\Class_::MODIFIER_READONLY)) {
            $this->profile->addFeature('readonly_classes', PhpVersion::PHP_82);
        }

        // PHP 8.2: DNF types (Disjunctive Normal Form)
        // Detected as UnionType containing IntersectionType
        if ($node instanceof Node\UnionType) {
            foreach ($node->types as $type) {
                if ($type instanceof Node\IntersectionType) {
                    $this->profile->addFeature('dnf_types', PhpVersion::PHP_82);
                    break;
                }
            }
        }

        // PHP 8.3: Typed class constants
        if ($node instanceof Node\Stmt\ClassConst && $node->type !== null) {
            $this->profile->addFeature('typed_class_constants', PhpVersion::PHP_83);
        }

        return null;
    }
}
