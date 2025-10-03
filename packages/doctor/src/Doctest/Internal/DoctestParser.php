<?php declare(strict_types=1);

namespace Cognesy\Doctor\Doctest\Internal;

use Cognesy\Doctor\Doctest\Nodes\DoctestCodeNode;
use Cognesy\Doctor\Doctest\Nodes\DoctestIdNode;
use Cognesy\Doctor\Doctest\Nodes\DoctestNode;
use Cognesy\Doctor\Doctest\Nodes\DoctestRegionNode;

final class DoctestParser
{
    private \Iterator $tokens;
    private ?DoctestToken $currentToken = null;
    private bool $hasMoreTokens = true;

    public function parse(iterable $tokens): \Generator {
        // Convert iterable to Iterator for consistent interface
        $this->tokens = match (true) {
            $tokens instanceof \Iterator => $tokens,
            is_array($tokens) => new \ArrayIterator($tokens),
            default => (function () use ($tokens) {
                yield from $tokens;
            })()
        };

        $this->hasMoreTokens = true;
        $this->advance();

        while ($this->hasMoreTokens && $this->currentToken !== null && $this->currentToken->type !== DoctestTokenType::EOF) {
            $node = match ($this->currentToken->type) {
                DoctestTokenType::DoctestId => $this->parseDoctestId(),
                DoctestTokenType::DoctestRegionStart => $this->parseDoctestRegion(),
                DoctestTokenType::Code => $this->parseCode(),
                DoctestTokenType::Comment => $this->parseCode(), // Treat comments as code for now
                DoctestTokenType::Newline => null, // Skip newlines in parsing
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

    private function parseDoctestId(): ?DoctestNode {
        $token = $this->currentToken;
        $id = $token->metadata['id'] ?? '';

        if (empty($id)) {
            return null;
        }

        return new DoctestIdNode($id, $token->line, $token->line);
    }

    private function parseDoctestRegion(): ?DoctestNode {
        $startToken = $this->currentToken;
        $regionName = $startToken->metadata['name'] ?? '';

        if (empty($regionName)) {
            return null;
        }

        $startLine = $startToken->line;
        $content = '';

        // Advance past the region start token
        $this->advance();

        // Collect content until we find the region end
        while ($this->hasMoreTokens &&
            $this->currentToken !== null &&
            $this->currentToken->type !== DoctestTokenType::DoctestRegionEnd &&
            $this->currentToken->type !== DoctestTokenType::EOF) {

            if ($this->currentToken->type === DoctestTokenType::Newline) {
                $content .= "\n";
            } else {
                $content .= $this->currentToken->value;
            }

            $this->advance();
        }

        $endLine = $this->currentToken?->line ?? $startLine;

        return new DoctestRegionNode($regionName, trim($content), $startLine, $endLine);
    }

    private function parseCode(): DoctestNode {
        $token = $this->currentToken;
        assert($token !== null);
        return new DoctestCodeNode($token->value, $token->line, $token->line);
    }
}