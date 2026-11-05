<?php

declare(strict_types=1);

namespace SimonSchaufi\Scanner\Domain\Model;

use SimonSchaufi\Scanner\Result\ScanResult;
use SimonSchaufi\Scanner\Utility;

/**
 * @extends \ArrayObject<int, ScanResult>
 */
class DirectoryResult extends \ArrayObject
{
    private readonly string $path;
    private int $numberOfIgnoredFiles = 0;
    private int $effectiveCodeLines = 0;
    private int $ignoredLines = 0;

    public function __construct(string $path, ScanResult ...$scanResult)
    {
        parent::__construct(array_values($scanResult));
        $this->path = Utility::ensureTrailingDirectorySeparator($path);
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function countAll(): int
    {
        return array_sum(
            array_map(
                static fn (ScanResult $scanResult): int => count($scanResult->getMatches()),
                $this->getArrayCopy()
            )
        );
    }

    public function getNumberOfIgnoredFiles(): int
    {
        return $this->numberOfIgnoredFiles;
    }

    public function setNumberOfIgnoredFiles(int $numberOfIgnoredFiles): void
    {
        $this->numberOfIgnoredFiles = $numberOfIgnoredFiles;
    }

    public function getEffectiveCodeLines(): int
    {
        return $this->effectiveCodeLines;
    }

    public function setEffectiveCodeLines(int $effectiveCodeLines): void
    {
        $this->effectiveCodeLines = $effectiveCodeLines;
    }

    public function getIgnoredLines(): int
    {
        return $this->ignoredLines;
    }

    public function setIgnoredLines(int $ignoredLines): void
    {
        $this->ignoredLines = $ignoredLines;
    }
}
