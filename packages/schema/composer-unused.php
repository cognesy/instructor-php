<?php declare(strict_types=1);

use ComposerUnused\ComposerUnused\Configuration\Configuration;
use ComposerUnused\ComposerUnused\Configuration\NamedFilter;

return static function(Configuration $config): Configuration {
    // Symfony's PhpStanExtractor loads these parsers internally at runtime.
    return $config
        ->addNamedFilter(NamedFilter::fromString('phpdocumentor/type-resolver'))
        ->addNamedFilter(NamedFilter::fromString('phpstan/phpdoc-parser'));
};
