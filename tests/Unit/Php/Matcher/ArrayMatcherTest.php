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

use SimonSchaufi\Scanner\Php\Matcher\ArrayMatcher;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PhpParser\PhpVersion;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ArrayMatcherTest extends TestCase
{
    #[Test]
    public function hitsFromFixtureAreFound(): void
    {
        $parser = (new ParserFactory())->createForVersion(PhpVersion::fromComponents(8, 3));
        $fixtureFile = __DIR__ . '/Fixtures/ArrayMatcherFixture.php';
        $statements = $parser->parse((string)file_get_contents($fixtureFile));
        self::assertIsArray($statements, 'Code could not be parsed.');

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());

        $configuration = [
            'ctrl/dividers2tabs' => [
                'restFiles' => [
                    'Breaking-62833-Dividers2Tabs.rst',
                ],
            ],
            'ctrl/versioningWS' => [
                'matchOnValues' => [1, 2],
                'restFiles' => [
                    'Breaking-24449-UseMovePlaceholdersAsDefaultInWorkspaces.rst',
                ],
            ],
        ];
        $subject = new ArrayMatcher($configuration);
        $traverser->addVisitor($subject);
        $traverser->traverse($statements);
        $expectedHitLineNumbers = [
            31,
            38,
            46,
            53,
        ];
        $actualHitLineNumbers = [];
        foreach ($subject->getMatches() as $hit) {
            $actualHitLineNumbers[] = $hit['line'];
        }
        self::assertEquals($expectedHitLineNumbers, $actualHitLineNumbers);
    }
}
