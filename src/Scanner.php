<?php

namespace SimonSchaufi\Scanner;

use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use SimonSchaufi\Scanner\Domain\Model\DirectoryResult;
use SimonSchaufi\Scanner\Domain\Model\MatcherBundleCollection;
use SimonSchaufi\Scanner\Domain\Model\ScanMatch;
use SimonSchaufi\Scanner\Php\MatcherFactory;
use SimonSchaufi\Scanner\Php\Visitor\CodeStatistics;
use SimonSchaufi\Scanner\Php\Visitor\GeneratorClassesResolver;
use SimonSchaufi\Scanner\Result\Indicator;
use SimonSchaufi\Scanner\Result\ScanResult;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class Scanner
{
    /**
     * Matcher configurations with their files already loaded, see getResolvedMatcherConfigurations().
     *
     * @var array<int, array{class?: string, configurationFile?: string, configurationArray?: mixed}>|null
     */
    private ?array $resolvedMatcherConfigurations = null;

    /**
     * @var array<string, int>
     */
    private array $typeRanking = [
        ScanMatch::TYPE_BREAKING => 100,
        ScanMatch::TYPE_DEPRECATION => 80,
        ScanMatch::TYPE_IMPORTANT => 50,
        ScanMatch::TYPE_FEATURE => 20,
    ];

    public function __construct(
        private readonly Finder $finder,
        private readonly Parser $parser,
        private readonly MatcherFactory $matcherFactory
    ) {
    }

    /**
     * @param string[] $excludeDirs
     */
    public function scanPath(
        string $path,
        MatcherBundleCollection $collection,
        array $excludeDirs = [],
    ): DirectoryResult {
        if (!is_dir($path)) {
            throw new \RuntimeException(
                sprintf('Path "%s" does not exist or is no directory.', $path),
                1507203233
            );
        }

        $directoryResult = new DirectoryResult($path);
        $files = $this->finder
            ->files()
            ->ignoreUnreadableDirs()
            ->in(Utility::removeTrailingDirectorySeparator($path))
            ->exclude($excludeDirs)
            ->name('*.php')
            ->sortByName();

        $numberOfIgnoredFiles = 0;
        $numberOfEffectiveCodeLines = 0;
        $numberOfIgnoreLines = 0;

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            $scanResult = $this->scanFile($file->getPathname(), $collection);
            if ($scanResult->isFileIgnored()) {
                $numberOfIgnoredFiles++;
                continue;
            }

            $numberOfEffectiveCodeLines += $scanResult->getEffectiveCodeLines();
            $numberOfIgnoreLines += $scanResult->getIgnoredLines();

            if ($scanResult->getMatches() !== []) {
                $directoryResult[] = $scanResult;
            }
        }

        $directoryResult->setNumberOfIgnoredFiles($numberOfIgnoredFiles);
        $directoryResult->setEffectiveCodeLines($numberOfEffectiveCodeLines);
        $directoryResult->setIgnoredLines($numberOfIgnoreLines);

        return $directoryResult;
    }

    public function scanFile(string $absoluteFilePath, MatcherBundleCollection $collection): ScanResult
    {
        if (!is_file($absoluteFilePath) || !is_readable($absoluteFilePath)) {
            throw new \RuntimeException(
                'File ' . $absoluteFilePath . ' does not exist or is not readable.',
                1790540102
            );
        }

        try {
            $statements = $this->parser->parse((string)file_get_contents($absoluteFilePath));
        } catch (Error $error) {
            return new ScanResult($absoluteFilePath, [], $error->getMessage(), false, 0, 0);
        }
        if ($statements === null) {
            return new ScanResult($absoluteFilePath, [], 'File could not be parsed.', false, 0, 0);
        }

        // The built in NameResolver translates class names shortened with 'use' to fully
        // qualified class names at all places. IMPORTANT: first process completely to resolve
        // fully qualified names of arguments, otherwise GeneratorClassesResolver will NOT get
        // reliable results.
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $statements = $traverser->traverse($statements);

        // IMPORTANT: second process to actually work on the pre-resolved statements
        $traverser = new NodeTraverser();
        // Understand GeneralUtility::makeInstance('My\\Package\\Foo\\Bar') as fqdn class name
        // in first argument
        $traverser->addVisitor(new GeneratorClassesResolver());
        // Count ignored lines, effective code lines, ...
        $statistics = new CodeStatistics();
        $traverser->addVisitor($statistics);

        $matchers = $this->matcherFactory->createAll($this->getResolvedMatcherConfigurations($collection));
        foreach ($matchers as $matcher) {
            $traverser->addVisitor($matcher);
        }
        $traverser->traverse($statements);

        return new ScanResult(
            $absoluteFilePath,
            $this->buildMatches($matchers),
            null,
            $statistics->isFileIgnored(),
            $statistics->getNumberOfEffectiveCodeLines(),
            $statistics->getNumberOfIgnoredLines()
        );
    }

    /**
     * The registry declares each matcher with a configuration file. Requiring those files
     * for every scanned file costs seconds over a whole extension and buys nothing, so the
     * loaded configurations are kept and handed to the factory as configurationArray.
     *
     * @return array<int, array{class?: string, configurationFile?: string, configurationArray?: mixed}>
     * @throws \RuntimeException if a declared configuration file is missing or malformed
     */
    private function getResolvedMatcherConfigurations(MatcherBundleCollection $collection): array
    {
        if ($this->resolvedMatcherConfigurations !== null) {
            return $this->resolvedMatcherConfigurations;
        }

        $resolved = $collection->getConfiguration();

        return $this->resolvedMatcherConfigurations = $resolved;
    }

    /**
     * @param CodeScannerInterface[]|NodeVisitor[] $matchers
     * @return list<ScanMatch>
     */
    private function buildMatches(array $matchers): array
    {
        $matches = [];
        foreach ($matchers as $matcher) {
            /** @var CodeScannerInterface $matcher */
            $matcherName = $this->getShortClassName($matcher::class);
            foreach ($matcher->getMatches() as $match) {
                $restFiles = array_values($match['restFiles']);
                $type = null;
                if ($restFiles !== []) {
                    $type = $this->inferTypeFromRestFiles($restFiles);
                }
                $scanMatch = new ScanMatch(
                    $matcherName,
                    Indicator::from($match['indicator']),
                    $match['message'],
                    $match['line'],
                    $restFiles,
                    $type ?? ScanMatch::TYPE_IMPORTANT
                );
                $matches[] = $scanMatch;
            }
        }

        return $matches;
    }

    private function getShortClassName(string $fullyQualifiedClassName): string
    {
        $separatorPosition = strrpos($fullyQualifiedClassName, '\\');

        return $separatorPosition === false
            ? $fullyQualifiedClassName
            : substr($fullyQualifiedClassName, $separatorPosition + 1);
    }

    /**
     * @param list<string> $restFiles
     */
    private function inferTypeFromRestFiles(array $restFiles): string
    {
        $type = '';
        foreach ($restFiles as $restFile) {
            $fileType = $this->extractFileType($restFile);
            $type = $this->getHigherType($type, $fileType);
            if ($type === ScanMatch::TYPE_BREAKING) {
                // breaking is the highest type, return if this type is found.
                break;
            }
        }
        return $type;
    }

    private function extractFileType(string $restFile): string
    {
        [$fileType] = explode('-', basename($restFile));
        return strtoupper($fileType);
    }

    private function getHigherType(string $type, string $fileType): string
    {
        if ($type === '') {
            return $fileType;
        }
        return ($this->typeRanking[$fileType] > $this->typeRanking[$type]) ? $fileType : $type;
    }
}
