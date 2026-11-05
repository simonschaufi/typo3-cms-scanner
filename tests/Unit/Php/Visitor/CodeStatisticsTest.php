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

namespace SimonSchaufi\Scanner\Tests\Unit\Php\Visitor;

use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PhpVersion;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SimonSchaufi\Scanner\Php\Visitor\CodeStatistics;

final class CodeStatisticsTest extends TestCase
{
    #[Test]
    public function enterNodeSumsStatistics(): void
    {
        $parser = (new ParserFactory())->createForVersion(PhpVersion::fromComponents(8, 3));
        $fixtureFile = __DIR__ . '/Fixtures/CodeStatisticsFixture.php';
        $statements = $parser->parse((string)file_get_contents($fixtureFile));
        self::assertIsArray($statements, 'Code could not be parsed.');

        $traverser = new NodeTraverser();
        $subject = new CodeStatistics();
        $traverser->addVisitor($subject);
        $traverser->traverse($statements);

        self::assertTrue($subject->isFileIgnored());
        self::assertEquals(2, $subject->getNumberOfIgnoredLines());
        self::assertEquals(7, $subject->getNumberOfEffectiveCodeLines());
    }
}
