<?php

declare(strict_types=1);

namespace SimonSchaufi\Scanner\Domain\Model;

use SimonSchaufi\Scanner\Utility;

readonly class MatcherBundle
{
    private string $configurationPath;

    /**
     * @var list<string>
     */
    private array $classNames;

    /**
     * @var array<int, array{class: string, configurationFile: string}>
     */
    private array $configuration;

    /**
     * @param list<class-string> $classNames Fully qualified matcher class names
     */
    public function __construct(
        string $configurationPath,
        array $classNames
    ) {
        if (!is_dir($configurationPath)) {
            throw new \RuntimeException(
                sprintf('Path "%s" does not exist', $configurationPath),
                1507203231
            );
        }
        if ($classNames === []) {
            throw new \RuntimeException(
                'No matcher class names given',
                1507203232
            );
        }

        $this->configurationPath = Utility::ensureTrailingDirectorySeparator($configurationPath);

        $this->classNames = $classNames;
        $this->configuration = $this->buildConfiguration();
    }

    /**
     * @return array<int, array{class: string, configurationFile: string}>
     */
    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    /**
     * @return array<int, array{class: string, configurationFile: string}>
     */
    private function buildConfiguration(): array
    {
        return array_map(
            function (string $matcherClassName) {
                $lastClassPart = $this->extractLastClassPart($matcherClassName);
                return [
                    'class' => $matcherClassName,
                    'configurationFile' => $this->configurationPath . $lastClassPart . '.php',
                ];
            },
            $this->classNames
        );
    }

    private function extractLastClassPart(string $className): string
    {
        $parts = explode('\\', $className);
        return array_pop($parts);
    }
}
