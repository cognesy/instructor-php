<?php declare(strict_types=1);

namespace Cognesy\Doctor\Markdown\Internal;

use Cognesy\Doctor\Markdown\MarkdownFile;
use Cognesy\Doctor\Markdown\Nodes\CodeBlockNode;
use Cognesy\Doctor\Markdown\Visitors\ReplaceCodeBlockById;
use Cognesy\Doctor\Markdown\Visitors\RetrieveCodeBlock;

final class CodeBlockManipulator
{
    public function __construct(
        private MarkdownFile $file,
        private string $id,
    ) {}

    public function withContent(string $content): MarkdownFile {
        $document = $this->file->root();
        $newDocument = $document->accept(new ReplaceCodeBlockById($this->id, $content));
        return $this->file->withRoot($newDocument);
    }

    public function content() : string {
        $document = $this->file->root();
        return $document->accept(new RetrieveCodeBlock($this->id))->content;
    }

    public function get() : CodeBlockNode {
        $document = $this->file->root();
        $node = $document->accept(new RetrieveCodeBlock($this->id));
        if (!$node instanceof CodeBlockNode) {
            throw new \RuntimeException("Code block with ID '{$this->id}' not found.");
        }
        return $node;
    }
}