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

use SimonSchaufi\Scanner\Php\Matcher\GlobalMatcher;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PhpParser\PhpVersion;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GlobalMatcherTest extends TestCase
{
    #[Test]
    public function hitsFromFixtureAreFound(): void
    {
        $parser = (new ParserFactory())->createForVersion(PhpVersion::fromComponents(8, 3));
        $fixtureFile = __DIR__ . '/Fixtures/GlobalMatcherFixture.php';
        $statements = $parser->parse((string)file_get_contents($fixtureFile));
        self::assertIsArray($statements, 'Code could not be parsed.');

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());

        $configuration = [
            '$TSFE' => [
                'restFiles' => [
                    'Breaking-61459-RemovalTslib.rst',
                ],
            ],
            '$TT' => [
                'restFiles' => [
                    'Breaking-61459-RemovalTslib.rst',
                ],
            ],
        ];
        $subject = new GlobalMatcher($configuration);
        $traverser->addVisitor($subject);
        $traverser->traverse($statements);
        $expectedHitLineNumbers = [
            28,
            29,
        ];
        $actualHitLineNumbers = [];
        foreach ($subject->getMatches() as $hit) {
            $actualHitLineNumbers[] = $hit['line'];
        }
        self::assertEquals($expectedHitLineNumbers, $actualHitLineNumbers);
    }
}
