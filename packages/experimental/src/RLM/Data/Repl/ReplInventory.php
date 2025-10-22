<?php declare(strict_types=1);

namespace Cognesy\Experimental\RLM\Data\Repl;

/**
 * Names/types only; never raw content. Keep prompt small.
 */
final readonly class ReplInventory
{
    /** @param string[] $variableNames @param string[] $artifactNamespaces */
    public function __construct(
        public array $variableNames,
        public array $artifactNamespaces,
    ) {}
}

