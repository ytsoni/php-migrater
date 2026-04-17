<?php

declare(strict_types=1);

namespace Ylab\PhpMigrater\Tests\Unit\Analyzer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\SplFileInfo;
use Ylab\PhpMigrater\Analyzer\VersionDetector;
use Ylab\PhpMigrater\Config\Configuration;
use Ylab\PhpMigrater\Config\PhpVersion;

final class VersionDetectorTest extends TestCase
{
    private VersionDetector $detector;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->detector = new VersionDetector();
        $this->tmpDir = sys_get_temp_dir() . '/php-migrater-vd-' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $files = glob($this->tmpDir . '/*');
        if (is_array($files)) {
            foreach ($files as $f) {
                unlink($f);
            }
        }
        rmdir($this->tmpDir);
    }

    public function testName(): void
    {
        $this->assertSame('version_detector', $this->detector->getName());
    }

    public function testAnalyzeReturnsEmpty(): void
    {
        $file = new SplFileInfo(__FILE__, '', basename(__FILE__));
        $config = Configuration::fromDefaults(PhpVersion::PHP_56, PhpVersion::PHP_84);
        $this->assertSame([], $this->detector->analyze($file, $config));
    }

    public function testDetectsShortArraySyntax(): void
    {
        $file = $this->createTempFile('<?php $a = [1, 2, 3];');
        $profile = $this->detector->detectVersion($file);

        $this->assertTrue($profile->hasFeature('short_array_syntax'));
        $this->assertFalse($profile->getDetectedMinimumVersion()->isOlderThan(PhpVersion::PHP_54));
    }

    public function testDetectsTraits(): void
    {
        $code = '<?php trait MyTrait { public function foo(): void {} } class Foo { use MyTrait; }';
        $file = $this->createTempFile($code);
        $profile = $this->detector->detectVersion($file);

        $this->assertTrue($profile->hasFeature('traits'));
    }

    public function testDetectsGenerators(): void
    {
        $code = '<?php function gen() { yield 1; }';
        $file = $this->createTempFile($code);
        $profile = $this->detector->detectVersion($file);

        $this->assertTrue($profile->hasFeature('generators'));
    }

    public function testDetectsVariadicParams(): void
    {
        $code = '<?php function foo(int ...$args) {}';
        $file = $this->createTempFile($code);
        $profile = $this->detector->detectVersion($file);

        $this->assertTrue($profile->hasFeature('variadic_params'));
    }

    public function testDetectsArgumentUnpacking(): void
    {
        $code = '<?php foo(...$args);';
        $file = $this->createTempFile($code);
        $profile = $this->detector->detectVersion($file);

        $this->assertTrue($profile->hasFeature('argument_unpacking'));
    }

    public function testDetectsReturnTypes(): void
    {
        $code = '<?php function foo(): int { return 1; }';
        $file = $this->createTempFile($code);
        $profile = $this->detector->detectVersion($file);

        $this->assertTrue($profile->hasFeature('return_types'));
    }

    public function testDetectsNullCoalesce(): void
    {
        $code = '<?php $x = $a ?? $b;';
        $file = $this->createTempFile($code);
        $profile = $this->detector->detectVersion($file);

        $this->assertTrue($profile->hasFeature('null_coalesce'));
    }

    public function testDetectsSpaceshipOperator(): void
    {
        $code = '<?php $x = $a <=> $b;';
        $file = $this->createTempFile($code);
        $profile = $this->detector->detectVersion($file);

        $this->assertTrue($profile->hasFeature('spaceship_operator'));
    }

    public function testDetectsScalarTypeHints(): void
    {
        $code = '<?php function foo(int $x, string $y) {}';
        $file = $this->createTempFile($code);
        $profile = $this->detector->detectVersion($file);

        $this->assertTrue($profile->hasFeature('scalar_type_hints'));
    }

    public function testDetectsNullableTypes(): void
    {
        $code = '<?php function foo(?int $x) {}';
        $file = $this->createTempFile($code);
        $profile = $this->detector->detectVersion($file);

        $this->assertTrue($profile->hasFeature('nullable_types'));
    }

    public function testDetectsVoidReturnType(): void
    {
        $code = '<?php function foo(): void {}';
        $file = $this->createTempFile($code);
        $profile = $this->detector->detectVersion($file);

        $this->assertTrue($profile->hasFeature('void_return_type'));
    }

    public function testDetectsTypedProperties(): void
    {
        $code = '<?php class Foo { public int $x; }';
        $file = $this->createTempFile($code);
        $profile = $this->detector->detectVersion($file);

        $this->assertTrue($profile->hasFeature('typed_properties'));
    }

    public function testDetectsArrowFunctions(): void
    {
        $code = '<?php $fn = fn($x) => $x * 2;';
        $file = $this->createTempFile($code);
        $profile = $this->detector->detectVersion($file);

        $this->assertTrue($profile->hasFeature('arrow_functions'));
    }

    public function testDetectsNullCoalesceAssign(): void
    {
        $code = '<?php $x ??= 5;';
        $file = $this->createTempFile($code);
        $profile = $this->detector->detectVersion($file);

        $this->assertTrue($profile->hasFeature('null_coalesce_assign'));
    }

    public function testDetectsNamedArguments(): void
    {
        $code = '<?php array_slice(array: [1,2,3], offset: 1);';
        $file = $this->createTempFile($code);
        $profile = $this->detector->detectVersion($file);

        $this->assertTrue($profile->hasFeature('named_arguments'));
    }

    public function testDetectsMatchExpression(): void
    {
        $code = '<?php $x = match(1) { 1 => "one", default => "other" };';
        $file = $this->createTempFile($code);
        $profile = $this->detector->detectVersion($file);

        $this->assertTrue($profile->hasFeature('match_expression'));
    }

    public function testDetectsNullsafeOperator(): void
    {
        $code = '<?php $x = $obj?->foo;';
        $file = $this->createTempFile($code);
        $profile = $this->detector->detectVersion($file);

        $this->assertTrue($profile->hasFeature('nullsafe_operator'));
    }

    public function testDetectsUnionTypes(): void
    {
        $code = '<?php function foo(int|string $x) {}';
        $file = $this->createTempFile($code);
        $profile = $this->detector->detectVersion($file);

        $this->assertTrue($profile->hasFeature('union_types'));
    }

    public function testDetectsConstructorPromotion(): void
    {
        $code = '<?php class Foo { public function __construct(public int $x) {} }';
        $file = $this->createTempFile($code);
        $profile = $this->detector->detectVersion($file);

        $this->assertTrue($profile->hasFeature('constructor_promotion'));
    }

    public function testDetectsAttributes(): void
    {
        $code = '<?php #[Attribute] class Foo {}';
        $file = $this->createTempFile($code);
        $profile = $this->detector->detectVersion($file);

        $this->assertTrue($profile->hasFeature('attributes'));
    }

    public function testDetectsEnums(): void
    {
        $code = '<?php enum Color { case Red; case Green; }';
        $file = $this->createTempFile($code);
        $profile = $this->detector->detectVersion($file);

        $this->assertTrue($profile->hasFeature('enums'));
    }

    public function testDetectsReadonlyProperties(): void
    {
        $code = '<?php class Foo { public readonly int $x; }';
        $file = $this->createTempFile($code);
        $profile = $this->detector->detectVersion($file);

        $this->assertTrue($profile->hasFeature('readonly_properties'));
    }

    public function testDetectsIntersectionTypes(): void
    {
        $code = '<?php function foo(Iterator&Countable $x) {}';
        $file = $this->createTempFile($code);
        $profile = $this->detector->detectVersion($file);

        $this->assertTrue($profile->hasFeature('intersection_types'));
    }

    public function testDetectsNeverReturnType(): void
    {
        $code = '<?php function foo(): never { throw new \Exception(); }';
        $file = $this->createTempFile($code);
        $profile = $this->detector->detectVersion($file);

        $this->assertTrue($profile->hasFeature('never_return_type'));
    }

    public function testDetectsTypedClassConstants(): void
    {
        $code = '<?php class Foo { const int X = 1; }';
        $file = $this->createTempFile($code);
        $profile = $this->detector->detectVersion($file);

        $this->assertTrue($profile->hasFeature('typed_class_constants'));
    }

    public function testMinimumVersionTracksMostRecentFeature(): void
    {
        // Code using PHP 8.1 features should detect 8.1 as minimum
        $code = '<?php enum Status { case Active; case Inactive; }';
        $file = $this->createTempFile($code);
        $profile = $this->detector->detectVersion($file);

        $min = $profile->getDetectedMinimumVersion();
        $this->assertFalse($min->isOlderThan(PhpVersion::PHP_81));
    }

    public function testPlainPhp53Code(): void
    {
        $code = '<?php echo "hello";';
        $file = $this->createTempFile($code);
        $profile = $this->detector->detectVersion($file);

        $this->assertSame(PhpVersion::PHP_53, $profile->getDetectedMinimumVersion());
        $this->assertEmpty($profile->getFeatures());
    }

    public function testNonexistentFileReturnsEmptyProfile(): void
    {
        $file = new SplFileInfo($this->tmpDir . '/nonexistent.php', '', 'nonexistent.php');
        @$profile = $this->detector->detectVersion($file);

        $this->assertSame(PhpVersion::PHP_53, $profile->getDetectedMinimumVersion());
    }

    public function testInvalidPhpReturnsEmptyProfile(): void
    {
        $file = $this->createTempFile('<?php function { INVALID }}}');
        $profile = $this->detector->detectVersion($file);

        $this->assertSame(PhpVersion::PHP_53, $profile->getDetectedMinimumVersion());
    }

    public function testToArrayFormat(): void
    {
        $code = '<?php $a = [1]; $fn = fn() => 1;';
        $file = $this->createTempFile($code);
        $profile = $this->detector->detectVersion($file);

        $arr = $profile->toArray();
        $this->assertArrayHasKey('short_array_syntax', $arr);
        $this->assertArrayHasKey('arrow_functions', $arr);
        $this->assertSame('5.4', $arr['short_array_syntax']);
        $this->assertSame('7.4', $arr['arrow_functions']);
    }

    private function createTempFile(string $content): SplFileInfo
    {
        $path = $this->tmpDir . '/test_' . uniqid() . '.php';
        file_put_contents($path, $content);
        return new SplFileInfo($path, '', basename($path));
    }
}
