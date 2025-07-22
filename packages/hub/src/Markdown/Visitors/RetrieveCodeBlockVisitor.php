<?php

namespace Cognesy\InstructorHub\Markdown\Visitors;

use Cognesy\InstructorHub\Markdown\Contracts\NodeVisitor;
use Cognesy\InstructorHub\Markdown\Nodes\CodeBlockNode;
use Cognesy\InstructorHub\Markdown\Nodes\DocumentNode;

class RetrieveCodeBlockVisitor implements NodeVisitor
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