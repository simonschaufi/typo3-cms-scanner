<?php

namespace SimonSchaufi\Scanner;

use PhpParser\ParserFactory;
use PhpParser\PhpVersion;
use SimonSchaufi\Scanner\Php\MatcherFactory;
use Symfony\Component\Finder\Finder;

final readonly class ScannerFactory
{
    public static function create(): self
    {
        return new self(
            new ParserFactory(),
            new MatcherFactory(),
            new Finder()
        );
    }

    public function __construct(
        private ParserFactory $parserFactory,
        private MatcherFactory $matcherFactory,
        private Finder $finder
    ) {
    }

    public function createFor(int $major = 8, int $minor = 3): Scanner
    {
        $parser = $this->parserFactory->createForVersion(PhpVersion::fromComponents($major, $minor));

        return new Scanner(
            $this->finder,
            $parser,
            $this->matcherFactory
        );
    }
}
