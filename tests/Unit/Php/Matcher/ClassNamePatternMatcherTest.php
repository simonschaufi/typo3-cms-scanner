<?php

declare(strict_types=1);

namespace SimonSchaufi\Scanner\Tests\Unit\Php\Matcher;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PhpParser\PhpVersion;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SimonSchaufi\Scanner\Php\Matcher\ClassNamePatternMatcher;
use SimonSchaufi\Scanner\Php\Visitor\GeneratorClassesResolver;

final class ClassNamePatternMatcherTest extends TestCase
{
    #[Test]
    public function hitsFromFixtureAreFound(): void
    {
        $parser = (new ParserFactory())->createForVersion(PhpVersion::fromComponents(8, 3));
        $fixtureFile = __DIR__ . '/Fixtures/ClassNamePatternMatcherFixture.php';
        $statements = $parser->parse((string)file_get_contents($fixtureFile));
        self::assertIsArray($statements, 'Code could not be parsed.');

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $traverser->addVisitor(new GeneratorClassesResolver());

        $configuration = [
            '#^t3lib_#' => [
                'restFiles' => [
                    'Deprecation-legacy-files.md',
                ],
            ],
            '#^tslib_#' => [
                'restFiles' => [
                    'Deprecation-legacy-files.md',
                ],
            ],
            '#^tx_#i' => [
                'restFiles' => [
                    'Deprecation-non-namespaced.md',
                ],
            ],
        ];
        $subject = new ClassNamePatternMatcher($configuration);
        $traverser->addVisitor($subject);
        $traverser->traverse($statements);
        $expectedHitLineNumbers = [
            28,
            29,
            32,
            35,
            38,
            50,
        ];
        $actualHitLineNumbers = [];
        foreach ($subject->getMatches() as $hit) {
            $actualHitLineNumbers[] = $hit['line'];
        }
        self::assertEquals($expectedHitLineNumbers, $actualHitLineNumbers);
    }

    #[Test]
    public function noHitWithoutMatchingPattern(): void
    {
        $parser = (new ParserFactory())->createForVersion(PhpVersion::fromComponents(8, 3));
        $fixtureFile = __DIR__ . '/Fixtures/ClassNamePatternMatcherFixture.php';
        $statements = $parser->parse(file_get_contents($fixtureFile));

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());

        $configuration = [
            '#^there_is_no_such_class_#' => [
                'restFiles' => [
                    'Deprecation-legacy-files.md',
                ],
            ],
        ];
        $subject = new ClassNamePatternMatcher($configuration);
        $traverser->addVisitor($subject);
        $traverser->traverse($statements);

        self::assertEmpty($subject->getMatches());
    }
}
