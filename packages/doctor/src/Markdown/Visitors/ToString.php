<?php declare(strict_types=1);

namespace Cognesy\Doctor\Markdown\Visitors;

use Cognesy\Doctor\Markdown\Contracts\NodeVisitor;
use Cognesy\Doctor\Markdown\Enums\MetadataStyle;
use Cognesy\Doctor\Markdown\Internal\CodeBlockIdentifier;
use Cognesy\Doctor\Markdown\Nodes\CodeBlockNode;
use Cognesy\Doctor\Markdown\Nodes\ContentNode;
use Cognesy\Doctor\Markdown\Nodes\DocumentNode;
use Cognesy\Doctor\Markdown\Nodes\HeaderNode;
use Cognesy\Doctor\Markdown\Nodes\NewlineNode;
use Cognesy\Doctor\Markdown\Nodes\Node;
use Cognesy\Utils\ProgrammingLanguage;

final class ToString implements NodeVisitor
{
    public function __construct(
        private MetadataStyle $metadataStyle = MetadataStyle::Comments
    ) {
    }
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
        return match ($this->metadataStyle) {
            MetadataStyle::Fence => $this->renderCodeBlockWithFenceMetadata($node),
            MetadataStyle::Comments => $this->renderCodeBlockWithCommentMetadata($node),
        };
    }

    private function renderCodeBlockWithFenceMetadata(CodeBlockNode $node): string {
        // Build fence line with language and metadata
        $fenceLine = $node->language;
        
        // Add metadata to fence line (exclude 'id' as it's handled separately)
        $metadataForFence = array_filter($node->metadata, fn($key) => $key !== 'id', ARRAY_FILTER_USE_KEY);
        if (!empty($metadataForFence)) {
            $metadataString = $this->formatMetadataForFence($metadataForFence);
            $fenceLine .= ' ' . $metadataString;
        }
        
        // Prepare content and clean up existing @doctest lines
        $content = $this->prepareCodeBlockContent($node);
        $content = $this->removeExistingDoctestLines($content, $node->language);
        
        // Add ID as @doctest comment only if there's an ID in metadata
        if (isset($node->metadata['id'])) {
            $commentSyntax = ProgrammingLanguage::commentSyntax($node->language);
            $content = "{$commentSyntax} @doctest id=\"{$node->metadata['id']}\"\n{$content}";
        }
        
        return "```{$fenceLine}\n{$content}\n```";
    }

    private function renderCodeBlockWithCommentMetadata(CodeBlockNode $node): string {
        $fenceLine = $node->language;
        
        // Prepare content and clean up existing @doctest lines
        $content = $this->prepareCodeBlockContent($node);
        $content = $this->removeExistingDoctestLines($content, $node->language);
        
        $commentSyntax = ProgrammingLanguage::commentSyntax($node->language);
        
        // Add all metadata as @doctest comment
        if (!empty($node->metadata)) {
            $metadataString = $this->formatMetadataForComment($node->metadata);
            $content = "{$commentSyntax} @doctest {$metadataString}\n{$content}";
        } else {
            // Add just ID if no other metadata
            $id = CodeBlockIdentifier::extractRawId($node->id);
            if ($id !== '') {
                $content = "{$commentSyntax} @doctest id=\"{$id}\"\n{$content}";
            }
        }
        
        return "```{$fenceLine}\n{$content}\n```";
    }

    private function removeExistingDoctestLines(string $content, string $language): string {
        $contentLines = explode("\n", $content);
        $cleanedLines = [];
        $commentSyntax = ProgrammingLanguage::commentSyntax($language);
        $escapedSyntax = preg_quote($commentSyntax, '/');
        
        foreach ($contentLines as $line) {
            if (!preg_match("/^{$escapedSyntax}\s*@doctest\s/", $line)) {
                $cleanedLines[] = $line;
            }
        }
        
        return implode("\n", $cleanedLines);
    }

    private function prepareCodeBlockContent(CodeBlockNode $node): string {
        $content = $node->content;
        
        // Add PHP opening tag if present
        if ($node->hasPhpOpenTag) {
            $content = "<?php" . $content;
        }
        
        // Add PHP closing tag if present
        if ($node->hasPhpCloseTag) {
            $content = $content . "?>";
        }
        
        return $content;
    }

    private function formatMetadataForFence(array $metadata): string {
        $parts = [];
        foreach ($metadata as $key => $value) {
            $parts[] = $this->formatMetadataKeyValue($key, $value);
        }
        return implode(' ', $parts);
    }

    private function formatMetadataForComment(array $metadata): string {
        $parts = [];
        foreach ($metadata as $key => $value) {
            $parts[] = $this->formatMetadataKeyValue($key, $value);
        }
        return implode(' ', $parts);
    }

    private function formatMetadataKeyValue(string $key, mixed $value): string {
        if (is_bool($value)) {
            return "{$key}=" . ($value ? 'true' : 'false');
        }
        
        if (is_array($value)) {
            $arrayStr = '[' . implode(', ', array_map(fn($v) => is_string($v) ? "\"{$v}\"" : (string) $v, $value)) . ']';
            return "{$key}={$arrayStr}";
        }
        
        if (is_string($value)) {
            return "{$key}=\"{$value}\"";
        }
        
        return "{$key}={$value}";
    }
}