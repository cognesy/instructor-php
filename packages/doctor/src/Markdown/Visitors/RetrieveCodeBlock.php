<?php

namespace Cognesy\Doctor\Markdown\Visitors;

use Cognesy\Doctor\Markdown\Contracts\NodeVisitor;
use Cognesy\Doctor\Markdown\Nodes\CodeBlockNode;
use Cognesy\Doctor\Markdown\Nodes\DocumentNode;

class RetrieveCodeBlock implements NodeVisitor
{
    public function __construct(
        private string $id,
    ) {}

    public function visit($node): mixed {
        return match(true) {
            $node instanceof DocumentNode => array_reduce($node->children, fn($carry, $n) => $carry ?? $n->accept($this), null),
            $node instanceof CodeBlockNode => ($node->id === $this->id) ? $node : null,
            default => null,
        };
    }
}