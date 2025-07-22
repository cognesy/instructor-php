<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Markdown\Internal;

use Cognesy\InstructorHub\Markdown\MarkdownFile;
use Cognesy\InstructorHub\Markdown\Nodes\CodeBlockNode;
use Cognesy\InstructorHub\Markdown\Visitors\ReplaceCodeBlockVisitor;
use Cognesy\InstructorHub\Markdown\Visitors\RetrieveCodeBlockVisitor;

final class CodeBlockManipulator
{
    public function __construct(
        private MarkdownFile $file,
        private string $id,
    ) {}

    public function withContent(string $content): MarkdownFile {
        $document = $this->file->root();
        $newDocument = $document->accept(new ReplaceCodeBlockVisitor($this->id, $content));
        return $this->file->withRoot($newDocument);
    }

    public function content() : string {
        $document = $this->file->root();
        return $document->accept(new RetrieveCodeBlockVisitor($this->id))->content;
    }

    public function node() : CodeBlockNode {
        $document = $this->file->root();
        $node = $document->accept(new RetrieveCodeBlockVisitor($this->id));
        if (!$node instanceof CodeBlockNode) {
            throw new \RuntimeException("Code block with ID '{$this->id}' not found.");
        }
        return $node;
    }
}