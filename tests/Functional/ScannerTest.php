<?php

declare(strict_types=1);

namespace SimonSchaufi\Scanner\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SimonSchaufi\Scanner\Domain\Model\MatcherBundle;
use SimonSchaufi\Scanner\Domain\Model\MatcherBundleCollection;
use SimonSchaufi\Scanner\Domain\Model\ScanMatch;
use SimonSchaufi\Scanner\Php\Matcher;
use SimonSchaufi\Scanner\Result\ScanResult;
use SimonSchaufi\Scanner\ScannerFactory;

final class ScannerTest extends TestCase
{
    private const string FIXTURES_PATH = __DIR__ . '/Fixtures';

    private MatcherBundleCollection $collection;

    protected function setUp(): void
    {
        $this->collection = new MatcherBundleCollection(
            new MatcherBundle(
                __DIR__ . '/../../config/Matcher/v14',
                [
                    Matcher\ArrayDimensionMatcher::class,
                    Matcher\ArrayGlobalMatcher::class,
                    Matcher\ClassConstantMatcher::class,
                    Matcher\ClassNameMatcher::class,
                    Matcher\ConstantMatcher::class,
                    Matcher\ConstructorArgumentMatcher::class,
                    Matcher\PropertyAnnotationMatcher::class,
                    Matcher\MethodAnnotationMatcher::class,
                    Matcher\FunctionCallMatcher::class,
                    Matcher\AbstractMethodImplementationMatcher::class,
                    Matcher\InterfaceMethodChangedMatcher::class,
                    Matcher\MethodArgumentDroppedMatcher::class,
                    Matcher\MethodArgumentDroppedStaticMatcher::class,
                    Matcher\MethodArgumentRequiredMatcher::class,
                    Matcher\MethodArgumentRequiredStaticMatcher::class,
                    Matcher\MethodArgumentUnusedMatcher::class,
                    Matcher\MethodCallMatcher::class,
                    Matcher\MethodCallArgumentValueMatcher::class,
                    Matcher\MethodCallStaticMatcher::class,
                    Matcher\PropertyExistsStaticMatcher::class,
                    Matcher\PropertyProtectedMatcher::class,
                    Matcher\PropertyPublicMatcher::class,
                    Matcher\ScalarStringMatcher::class,
                ]
            )
        );
    }

    #[Test]
    public function scanPathFindsMatchesInFixturesDirectory(): void
    {
        $scanner = ScannerFactory::create()->createFor(8, 3);

        $result = $scanner->scanPath(self::FIXTURES_PATH, $this->collection);

        self::assertGreaterThan(0, $result->countAll(), 'Expected at least one match across all fixture files.');
    }

    #[Test]
    public function scanPathReturnsNoMatchesForCleanFile(): void
    {
        $scanner = ScannerFactory::create()->createFor(8, 3);

        $result = $scanner->scanPath(self::FIXTURES_PATH, $this->collection);

        $cleanFilePath = realpath(self::FIXTURES_PATH . '/CleanCodeFixture.php');
        self::assertNotFalse($cleanFilePath);

        /** @var ScanResult $scanResult */
        foreach ($result as $scanResult) {
            if ($scanResult->getFile() === $cleanFilePath) {
                self::fail('CleanCodeFixture.php should produce no matches, but it did.');
            }
        }
    }

    #[Test]
    public function scanFileFindsDeprecatedMethodCallsInFixture(): void
    {
        $scanner = ScannerFactory::create()->createFor(8, 3);
        $fixturePath = self::FIXTURES_PATH . '/DeprecatedMethodCallFixture.php';

        $scanResult = $scanner->scanFile($fixturePath, $this->collection);

        self::assertGreaterThan(
            0,
            count($scanResult->getMatches()),
            'Expected matches in DeprecatedMethodCallFixture.php but found none.'
        );
    }

    #[Test]
    public function scanFileFindsDeprecatedClassNameUsagesInFixture(): void
    {
        $scanner = ScannerFactory::create()->createFor(8, 3);
        $fixturePath = self::FIXTURES_PATH . '/DeprecatedClassNameFixture.php';

        $scanResult = $scanner->scanFile($fixturePath, $this->collection);

        self::assertGreaterThan(
            0,
            count($scanResult->getMatches()),
            'Expected matches in DeprecatedClassNameFixture.php but found none.'
        );
    }

    #[Test]
    public function scanFileReturnsNoMatchesForCleanFile(): void
    {
        $scanner = ScannerFactory::create()->createFor(8, 3);
        $fixturePath = self::FIXTURES_PATH . '/CleanCodeFixture.php';

        $scanResult = $scanner->scanFile($fixturePath, $this->collection);

        self::assertCount(0, $scanResult->getMatches(), 'Expected no matches in CleanCodeFixture.php.');
    }

    #[Test]
    public function scanFileMatchesCarryExpectedStructure(): void
    {
        $scanner = ScannerFactory::create()->createFor(8, 3);
        $fixturePath = self::FIXTURES_PATH . '/DeprecatedMethodCallFixture.php';

        $scanResult = $scanner->scanFile($fixturePath, $this->collection);

        foreach ($scanResult->getMatches() as $match) {
            self::assertInstanceOf(ScanMatch::class, $match);
            self::assertNotEmpty($match->getMatcher());
            self::assertGreaterThan(0, $match->getLine());
            self::assertNotEmpty($match->getMessage());
            self::assertContains($match->getType(), [
                ScanMatch::TYPE_BREAKING,
                ScanMatch::TYPE_DEPRECATION,
                ScanMatch::TYPE_IMPORTANT,
                ScanMatch::TYPE_FEATURE,
            ]);
            self::assertNotEmpty($match->getRestFiles(), 'Every match must reference at least one .rst file.');
        }
    }

    #[Test]
    public function scanFileDetectsDeprecatedDumpFileContents(): void
    {
        $scanner = ScannerFactory::create()->createFor(8, 3);
        $fixturePath = self::FIXTURES_PATH . '/DeprecatedMethodCallFixture.php';

        $scanResult = $scanner->scanFile($fixturePath, $this->collection);

        $messages = array_map(
            static fn (ScanMatch $m): string => $m->getMessage(),
            $scanResult->getMatches()
        );

        $found = array_filter(
            $messages,
            static fn (string $message): bool => str_contains($message, 'dumpFileContents')
        );

        self::assertNotEmpty($found, 'Expected a match for deprecated dumpFileContents() call.');
    }

    #[Test]
    public function scanPathDoesNotContainCleanFileInResults(): void
    {
        $scanner = ScannerFactory::create()->createFor(8, 3);

        $result = $scanner->scanPath(self::FIXTURES_PATH, $this->collection);

        $paths = array_map(
            static fn (ScanResult $scanResult): string => $scanResult->getFile(),
            $result->getArrayCopy()
        );

        $cleanFilePath = realpath(self::FIXTURES_PATH . '/CleanCodeFixture.php');
        self::assertNotFalse($cleanFilePath);

        self::assertNotContains(
            $cleanFilePath,
            $paths,
            'CleanCodeFixture.php should not appear in directory scan results.'
        );
    }

    #[Test]
    public function scanPathThrowsForNonExistentDirectory(): void
    {
        $scanner = ScannerFactory::create()->createFor(8, 3);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1507203233);

        $scanner->scanPath('/non/existent/path', $this->collection);
    }

    #[Test]
    public function scanFileThrowsForNonExistentFile(): void
    {
        $scanner = ScannerFactory::create()->createFor(8, 3);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1790540102);

        $scanner->scanFile('/non/existent/file.php', $this->collection);
    }
}
