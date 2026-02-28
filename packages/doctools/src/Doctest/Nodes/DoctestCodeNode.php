<?php declare(strict_types=1);

namespace Cognesy\Doctools\Doctest\Nodes;

final class DoctestCodeNode extends DoctestNode
{
    public function __construct(
        public readonly string $content,
        int $startLine,
        int $endLine,
    ) {
        parent::__construct($startLine, $endLine);
    }
}