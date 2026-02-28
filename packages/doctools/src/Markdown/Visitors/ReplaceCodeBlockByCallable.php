<?php declare(strict_types=1);

namespace Cognesy\Doctools\Markdown\Visitors;

use Closure;
use Cognesy\Doctools\Markdown\Contracts\NodeVisitor;
use Cognesy\Doctools\Markdown\Nodes\CodeBlockNode;
use Cognesy\Doctools\Markdown\Nodes\DocumentNode;
use Cognesy\Doctools\Markdown\Nodes\Node;

final class ReplaceCodeBlockByCallable implements NodeVisitor
{
    /**
     * @param \Closure(CodeBlockNode): CodeBlockNode $replacer
     */
    public function __construct(
        private Closure $replacer,
    ) {}

    #[\Override]
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