<?php declare(strict_types=1);

namespace Cognesy\Doctools\Markdown\Nodes;

use Cognesy\Doctools\Markdown\Contracts\NodeInterface;
use Cognesy\Doctools\Markdown\Contracts\NodeVisitor;

abstract readonly class Node implements NodeInterface
{
    public function __construct(
        public int $lineNumber = 0,
    ) {}

    #[\Override]
    public function accept(NodeVisitor $visitor): mixed {
        return $visitor->visit($this);
    }
}