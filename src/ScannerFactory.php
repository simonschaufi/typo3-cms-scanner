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
            new MatcherFactory()
        );
    }

    public function __construct(
        private ParserFactory $parserFactory,
        private MatcherFactory $matcherFactory
    ) {
    }

    public function createFor(int $major = 8, int $minor = 3): Scanner
    {
        $finder = new Finder();
        $parser = $this->parserFactory->createForVersion(PhpVersion::fromComponents($major, $minor));

        return new Scanner(
            $finder,
            $parser,
            $this->matcherFactory
        );
    }
}
