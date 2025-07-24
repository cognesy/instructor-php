<?php declare(strict_types=1);

namespace Cognesy\Doctor\Doctest\Nodes;

final class DoctestRegionNode extends DoctestNode
{
    public function __construct(
        public readonly string $name,
        public readonly string $content,
        int $startLine,
        int $endLine,
    ) {
        parent::__construct($startLine, $endLine);
    }
}