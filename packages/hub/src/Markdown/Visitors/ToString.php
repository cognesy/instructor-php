<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Markdown\Visitors;

use Cognesy\InstructorHub\Markdown\Contracts\NodeVisitor;
use Cognesy\InstructorHub\Markdown\Internal\CodeBlockIdentifier;
use Cognesy\InstructorHub\Markdown\Nodes\CodeBlockNode;
use Cognesy\InstructorHub\Markdown\Nodes\ContentNode;
use Cognesy\InstructorHub\Markdown\Nodes\DocumentNode;
use Cognesy\InstructorHub\Markdown\Nodes\HeaderNode;
use Cognesy\InstructorHub\Markdown\Nodes\NewlineNode;
use Cognesy\InstructorHub\Markdown\Nodes\Node;

final class ToString implements NodeVisitor
{
    public function visit(Node $node): string {
        return match (true) {
            $node instanceof DocumentNode => array_reduce($node->children, fn($carry, $n) => $carry . $n->accept($this), ''),
            $node instanceof HeaderNode => str_repeat('#', $node->level) . " {$node->content}",
            $node instanceof CodeBlockNode => $this->renderCodeBlock($node),
            $node instanceof ContentNode => $node->content,
            $node instanceof NewlineNode => "\n",
            default => '',
        };
    }

    private function renderCodeBlock(CodeBlockNode $node): string {
        // Extract the actual ID from the node ID (remove "codeblock_" prefix)
        $snippetId = str_replace('codeblock_', '', $node->id);
        
        // Start with clean content
        $content = $node->content;
        
        // Add PHP opening tag if present
        if ($node->hasPhpOpenTag) {
            $content = "<?php" . $content;
        }
        
        // Add PHP closing tag if present
        if ($node->hasPhpCloseTag) {
            $content = $content . "?>";
        }
        
        // Embed snippet ID using language-appropriate comment syntax
        $contentWithId = CodeBlockIdentifier::embedId($content, $snippetId, $node->language);
        
        return "```{$node->language}\n{$contentWithId}\n```";
    }
}