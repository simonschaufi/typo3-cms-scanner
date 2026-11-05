<?php

declare(strict_types=1);

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

namespace SimonSchaufi\Scanner\Tests\Unit\Php\Matcher;

use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PhpParser\PhpVersion;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SimonSchaufi\Scanner\Php\Matcher\ClassNameMatcher;
use SimonSchaufi\Scanner\Php\Visitor\GeneratorClassesResolver;

final class ClassNameMatcherTest extends TestCase
{
    #[Test]
    public function hitsFromFixtureAreFound(): void
    {
        $parser = (new ParserFactory())->createForVersion(PhpVersion::fromComponents(8, 3));
        $fixtureFile = __DIR__ . '/Fixtures/ClassNameMatcherFixture.php';
        $statements = $parser->parse((string)file_get_contents($fixtureFile));
        self::assertIsArray($statements, 'Code could not be parsed.');

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $traverser->addVisitor(new GeneratorClassesResolver());

        $configuration = [
            'RemoveXSS' => [
                'restFiles' => [
                    'Breaking-80700-DeprecatedFunctionalityRemoved.rst',
                    'Deprecation-76164-DeprecateRemoveXSS.rst',
                ],
            ],
            'TYPO3\CMS\Backend\Console\Application' => [
                'restFiles' => [
                    'Breaking-80700-DeprecatedFunctionalityRemoved.rst',
                    'Deprecation-80468-CommandLineInterfaceCliKeysAndCli_dispatchphpsh.rst',
                ],
            ],
        ];
        $subject = new ClassNameMatcher($configuration);
        $traverser->addVisitor($subject);
        $traverser->traverse($statements);
        $expectedHitLineNumbers = [
            30,
            30,
            30,
            32,
            35,
            36,
            37,
            38,
            39,
            40,
            41,
            42,
            43,
            44,
            47,
            48,
            60,
        ];
        $actualHitLineNumbers = [];
        foreach ($subject->getMatches() as $match) {
            $actualHitLineNumbers[] = $match['line'];
        }
        self::assertEquals($expectedHitLineNumbers, $actualHitLineNumbers);
    }
}
