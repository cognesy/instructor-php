<?php

namespace Cognesy\Doctools\Markdown\Visitors;

use Cognesy\Doctools\Markdown\Contracts\NodeVisitor;
use Cognesy\Doctools\Markdown\Nodes\CodeBlockNode;
use Cognesy\Doctools\Markdown\Nodes\DocumentNode;
use Cognesy\Doctools\Markdown\Nodes\Node;

class RetrieveCodeBlock implements NodeVisitor
{
    public function __construct(
        private string $id,
    ) {}

    #[\Override]
    public function visit(Node $node): mixed {
        return match(true) {
            $node instanceof DocumentNode => array_reduce($node->children, fn($carry, $n) => $carry ?? $n->accept($this), null),
            $node instanceof CodeBlockNode => ($node->id === $this->id) ? $node : null,
            default => null,
        };
    }
}