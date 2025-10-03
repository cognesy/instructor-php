<?php declare(strict_types=1);

namespace Cognesy\Doctor\Markdown\Internal;

final class Lexer
{
    private int $position = 0;
    private int $line = 1;
    private string $input = '';
    private int $length = 0;

    /**
     * Tokenizes the input string into a generator of tokens.
     *
     * @param string $input The input string to tokenize.
     * @return \Generator<Token> A generator yielding tokens lazily.
     */
    public function tokenize(string $input): \Generator {
        // Normalize line endings to \n only
        $this->input = str_replace(["\r\n", "\r"], "\n", $input);
        $this->length = strlen($this->input);
        $this->position = 0;
        $this->line = 1;

        while ($this->position < $this->length) {
            $char = $this->input[$this->position];

            $generator = match (true) {
                $char === '#' => $this->tokenizeHeader(),
                $this->isCodeBlockFence() => $this->tokenizeCodeBlockFence(),
                $char === "\n" => $this->tokenizeNewline(),
                default => $this->tokenizeContent(),
            };
            
            foreach ($generator as $token) {
                yield $token;
            }
        }
    }

    /**
     * Legacy method for backward compatibility.
     * @internal Use tokenize() which returns a Generator for better memory efficiency.
     */
    public function tokenizeToArray(string $input): array {
        return iterator_to_array($this->tokenize($input));
    }

    // INTERNAL ////////////////////////////////////////////////////////

    private function tokenizeHeader(): \Generator {
        $level = 0;
        $startPos = $this->position;

        while ($this->position < $this->length && $this->input[$this->position] === '#') {
            $level++;
            $this->position++;
        }

        if ($this->position < $this->length && $this->input[$this->position] === ' ') {
            $this->position++;
            $content = $this->consumeUntil("\n");
            $hashes = str_repeat('#', $level);
            yield new Token(TokenType::Header, "{$hashes} {$content}", $this->line);
        } else {
            $this->position = $startPos;
            yield from $this->tokenizeContent();
        }
    }

    private function isCodeBlockFence(): bool {
        return substr($this->input, $this->position, 3) === '```';
    }

    private function tokenizeCodeBlockFence(): \Generator {
        yield new Token(TokenType::CodeBlockFenceStart, '```', $this->line);
        $this->position += 3;
        $content = $this->consumeUntil("\n");
        if ($content !== '') {
            yield new Token(TokenType::CodeBlockFenceInfo, $content, $this->line);
        }

        // Skip the newline after fence info
        if ($this->position < $this->length && $this->input[$this->position] === "\n") {
            $this->position++;
            $this->line++;
        }

        // Tokenize code block content until closing fence
        $content = '';
        $startLine = $this->line;
        while ($this->position < $this->length && !$this->isCodeBlockFence()) {
            $content .= $this->input[$this->position];
            if ($this->input[$this->position] === "\n") {
                $this->line++;
            }
            $this->position++;
        }

        yield new Token(TokenType::CodeBlockContent, rtrim($content), $startLine);

        if ($this->isCodeBlockFence()) {
            yield new Token(TokenType::CodeBlockFenceEnd, '```', $this->line);
            $this->position += 3;
        }
    }

    private function tokenizeNewline(): \Generator {
        yield new Token(TokenType::Newline, "\n", $this->line);
        $this->position++;
        $this->line++;
    }

    private function tokenizeContent(): \Generator {
        $content = $this->consumeUntil("\n");
        if ($content !== '') {
            yield new Token(TokenType::Content, $content, $this->line);
        } else {
            // If no content was consumed, advance by one character to prevent infinite loop
            yield new Token(TokenType::Content, $this->input[$this->position], $this->line);
            $this->position++;
        }
    }

    private function consumeUntil(string $delimiters): string {
        $content = '';
        while ($this->position < $this->length && !str_contains($delimiters, $this->input[$this->position])) {
            $char = $this->input[$this->position];
            $content .= $char;
            if ($char === "\n") {
                $this->line++;
            }
            $this->position++;
        }
        return $content;
    }
}