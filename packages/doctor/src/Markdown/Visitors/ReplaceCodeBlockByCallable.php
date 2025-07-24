<?php declare(strict_types=1);

namespace Cognesy\Doctor\Markdown\Visitors;

use Closure;
use Cognesy\Doctor\Markdown\Contracts\NodeVisitor;
use Cognesy\Doctor\Markdown\Nodes\CodeBlockNode;
use Cognesy\Doctor\Markdown\Nodes\DocumentNode;
use Cognesy\Doctor\Markdown\Nodes\Node;

final class ReplaceCodeBlockByCallable implements NodeVisitor
{
    public function __construct(
        private Closure $replacer,
    ) {}

    public function visit(Node $node): Node {
        return match(true) {
            $node instanceof DocumentNode => $this->visitDocument($node),
            $node instanceof CodeBlockNode => $this->visitCodeBlock($node),
            default => $node,
        };
    }

    private function visitDocument(DocumentNode $node): DocumentNode {
        $newChildren = array_map(
            fn($child) => $child->accept($this),
            $node->children,
        );
        return new DocumentNode(...$newChildren);
    }

    private function visitCodeBlock(CodeBlockNode $node): Node {
        return ($this->replacer)($node);
    }
}