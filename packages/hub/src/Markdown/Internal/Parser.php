<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Markdown\Internal;

use Cognesy\InstructorHub\Markdown\Nodes\CodeBlockNode;
use Cognesy\InstructorHub\Markdown\Nodes\ContentNode;
use Cognesy\InstructorHub\Markdown\Nodes\DocumentNode;
use Cognesy\InstructorHub\Markdown\Nodes\HeaderNode;
use Cognesy\InstructorHub\Markdown\Nodes\NewlineNode;
use Cognesy\InstructorHub\Markdown\Nodes\Node;

final class Parser
{
    private \Iterator $tokens;
    private ?Token $currentToken = null;
    private bool $hasMoreTokens = true;

    public function parse(iterable $tokens): \Generator {
        // Convert iterable to Iterator for consistent interface
        $this->tokens = match (true) {
            $tokens instanceof \Iterator => $tokens,
            is_array($tokens) => new \ArrayIterator($tokens),
            default => (function() use ($tokens) { yield from $tokens; })()
        };
        
        $this->hasMoreTokens = true;
        $this->advance();

        while ($this->hasMoreTokens && $this->currentToken !== null) {
            $node = match ($this->currentToken->type) {
                TokenType::Header => $this->parseHeader(),
                TokenType::CodeBlockFenceStart => $this->parseCodeBlock(),
                TokenType::Content => $this->parseContent(),
                TokenType::Newline => new NewlineNode(),
                default => null,
            };

            if ($node !== null) {
                yield $node;
            }

            $this->advance();
        }
    }

    private function advance(): void {
        if ($this->tokens->valid()) {
            $this->currentToken = $this->tokens->current();
            $this->tokens->next();
        } else {
            $this->currentToken = null;
            $this->hasMoreTokens = false;
        }
    }

    // INTERNAL ////////////////////////////////////////////////////////

    private function parseHeader(): Node {
        $token = $this->currentToken;
        // extract header level and content
        if (preg_match('/^(#+)\s+(.+?)[\r\n]*$/', $token->value, $matches)) {
            $level = strlen($matches[1]);
            $content = $matches[2];
        } else {
            // Fallback for malformed headers
            $level = 1;
            $content = trim(ltrim($token->value, '# '));
        }

        return new HeaderNode($level, $content);
    }

    private function parseCodeBlock(): ?Node {
        // Current token is CodeBlockFenceStart, move to next
        $this->advance();
        
        // Check for optional CodeBlockFenceInfo (language and metadata)
        $language = '';
        $metadata = [];
        if ($this->hasMoreTokens && $this->currentToken !== null 
            && $this->currentToken->type === TokenType::CodeBlockFenceInfo) {
            $fenceInfo = $this->parseFenceInfo($this->currentToken->value);
            $language = $fenceInfo['language'];
            $metadata = $fenceInfo['metadata'];
            $this->advance();
        }
        
        // Expect CodeBlockContent
        if (!$this->hasMoreTokens || $this->currentToken === null
            || $this->currentToken->type !== TokenType::CodeBlockContent) {
            return null;
        }
        
        $contentToken = $this->currentToken;
        $this->advance();
        
        // Analyze content for PHP tags
        $content = $contentToken->value;
        $hasPhpOpenTag = str_starts_with($content, '<?php');
        $hasPhpCloseTag = str_ends_with($content, '?>');
        
        // Strip PHP tags to get clean content
        $contentWithoutPhpTags = $content;
        if ($hasPhpOpenTag) {
            $contentWithoutPhpTags = substr($contentWithoutPhpTags, 5); // Remove '\<\?php'
        }
        if ($hasPhpCloseTag) {
            $contentWithoutPhpTags = substr($contentWithoutPhpTags, 0, -2); // Remove '\?\>'
        }
        
        // Expect CodeBlockFenceEnd
        if (!$this->hasMoreTokens || $this->currentToken === null
            || $this->currentToken->type !== TokenType::CodeBlockFenceEnd) {
            return null;
        }
        
        // Extract or generate codeblock ID
        $codeblockId = CodeBlockIdentifier::extractId($content)
            ?: ($metadata['id'] ?? null)
            ?: CodeBlockIdentifier::generateId();
            
        return new CodeBlockNode(
            "codeblock_{$codeblockId}", 
            $language, 
            $contentWithoutPhpTags,  // Store clean content as main content
            $metadata,
            $hasPhpOpenTag,
            $hasPhpCloseTag,
            $content  // Store original content with PHP tags for reference if needed
        );
    }

    private function parseContent(): Node {
        $token = $this->currentToken;
        return new ContentNode($token->value);
    }

    private function parseFenceInfo(string $fenceInfo): array {
        $fenceInfo = trim($fenceInfo);
        $parts = [];
        $current = '';
        $inQuotes = false;
        $quoteChar = null;
        
        for ($i = 0; $i < strlen($fenceInfo); $i++) {
            $char = $fenceInfo[$i];
            
            if (!$inQuotes && ($char === '"' || $char === "'")) {
                $inQuotes = true;
                $quoteChar = $char;
                $current .= $char;
            } elseif ($inQuotes && $char === $quoteChar) {
                $inQuotes = false;
                $quoteChar = null;
                $current .= $char;
            } elseif (!$inQuotes && ctype_space($char)) {
                if ($current !== '') {
                    $parts[] = $current;
                    $current = '';
                }
            } else {
                $current .= $char;
            }
        }
        
        if ($current !== '') {
            $parts[] = $current;
        }
        
        $language = $parts[0] ?? '';
        $metadata = [];
        
        // Parse key=value pairs
        for ($i = 1; $i < count($parts); $i++) {
            if (str_contains($parts[$i], '=')) {
                [$key, $value] = explode('=', $parts[$i], 2);
                $metadata[$key] = $value;
            }
        }
        
        return [
            'language' => $language,
            'metadata' => $metadata,
        ];
    }
}