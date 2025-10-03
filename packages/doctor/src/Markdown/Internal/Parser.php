<?php declare(strict_types=1);

namespace Cognesy\Doctor\Markdown\Internal;

use Cognesy\Doctor\Markdown\Nodes\CodeBlockNode;
use Cognesy\Doctor\Markdown\Nodes\ContentNode;
use Cognesy\Doctor\Markdown\Nodes\HeaderNode;
use Cognesy\Doctor\Markdown\Nodes\NewlineNode;
use Cognesy\Doctor\Markdown\Nodes\Node;

final class Parser
{
    /** @var \Iterator<Token> */
    private \Iterator $tokens;
    private ?Token $currentToken = null;
    private bool $hasMoreTokens = true;

    /**
     * @param iterable<Token> $tokens
     * @return \Generator<Node>
     */
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
                TokenType::Newline => new NewlineNode($this->currentToken->line),
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
        assert($token !== null, 'Current token must not be null in parseHeader');
        // extract header level and content
        if (preg_match('/^(#+)\s+(.+?)[\r\n]*$/', $token->value, $matches)) {
            $level = strlen($matches[1]);
            $content = $matches[2];
        } else {
            // Fallback for malformed headers
            $level = 1;
            $content = trim(ltrim($token->value, '# '));
        }

        return new HeaderNode($level, $content, $token->line);
    }

    private function parseCodeBlock(): CodeBlockNode|null {
        assert($this->currentToken !== null, 'Current token must not be null in parseCodeBlock');
        // Current token is CodeBlockFenceStart, capture line number
        $codeBlockStartLine = $this->currentToken->line;
        $this->advance();
        
        // Check for optional CodeBlockFenceInfo (language and metadata)
        $language = '';
        $fenceMetadata = [];
        if ($this->hasMoreTokens && $this->currentToken !== null 
            && $this->currentToken->type === TokenType::CodeBlockFenceInfo) {
            $fenceInfo = CodeBlockMetadataParser::parseFenceLineMetadata($this->currentToken->value);
            $language = $fenceInfo['language'];
            $fenceMetadata = $fenceInfo['metadata'];
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
        if (!$this->hasMoreTokens || $this->currentToken === null) {
            return null;
        }
        if ($this->currentToken->type !== TokenType::CodeBlockFenceEnd) {
            return null;
        }

        // Extract metadata from different sources
        $extractedId = CodeBlockIdentifier::extractId($content);
        $doctestMetadata = CodeBlockMetadataParser::extractDoctestMetadata($content, $language);

        // Combine metadata with precedence: @doctest > fence params > extracted ID
        $combinedMetadata = CodeBlockMetadataParser::combineMetadata(
            $fenceMetadata,
            $doctestMetadata,
            $extractedId
        );

        // Generate codeblock ID using centralized method
        $fullId = CodeBlockIdentifier::createCodeBlockId($combinedMetadata['id'] ?? null);

        return new CodeBlockNode(
            $fullId,
            $language,
            $contentWithoutPhpTags,  // Store clean content as main content
            $combinedMetadata,
            $hasPhpOpenTag,
            $hasPhpCloseTag,
            $content,  // Store original content with PHP tags for reference if needed
            $codeBlockStartLine
        );
    }

    private function parseContent(): Node {
        $token = $this->currentToken;
        assert($token !== null, 'Current token must not be null in parseContent');
        return new ContentNode($token->value, $token->line);
    }

}