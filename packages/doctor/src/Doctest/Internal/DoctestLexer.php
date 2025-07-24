<?php declare(strict_types=1);

namespace Cognesy\Doctor\Doctest\Internal;

final class DoctestLexer
{
    private int $position = 0;
    private int $line = 1;
    private string $input;
    private int $length;
    private string $language;

    public function __construct(string $language = 'php')
    {
        $this->language = $language;
    }

    /**
     * Tokenizes the input string into a generator of tokens.
     *
     * @param string $input The input string to tokenize.
     * @return \Generator<DoctestToken> A generator yielding tokens lazily.
     */
    public function tokenize(string $input): \Generator
    {
        // Normalize line endings to \n only
        $this->input = str_replace(["\r\n", "\r"], "\n", $input);
        $this->length = strlen($this->input);
        $this->position = 0;
        $this->line = 1;

        while ($this->position < $this->length) {
            $char = $this->input[$this->position];

            $generator = match (true) {
                $this->isDoctestAnnotation() => $this->tokenizeDoctestAnnotation(),
                $this->isComment() => $this->tokenizeComment(),
                $char === "\n" => $this->tokenizeNewline(),
                default => $this->tokenizeCode(),
            };
            
            foreach ($generator as $token) {
                yield $token;
            }
        }

        yield new DoctestToken(DoctestTokenType::EOF, '', $this->line);
    }

    // INTERNAL ////////////////////////////////////////////////////////

    private function isDoctestAnnotation(): bool
    {
        $commentStart = $this->getCommentStart();
        if (!str_starts_with(substr($this->input, $this->position), $commentStart)) {
            return false;
        }
        
        $lineContent = $this->peekLine();
        return str_contains($lineContent, '@doctest');
    }

    private function isComment(): bool
    {
        $commentStart = $this->getCommentStart();
        return str_starts_with(substr($this->input, $this->position), $commentStart);
    }

    private function getCommentStart(): string
    {
        return match ($this->language) {
            'php', 'javascript', 'java', 'c', 'cpp', 'go', 'rust' => '//',
            'python', 'ruby', 'shell', 'bash' => '#',
            'html', 'xml' => '<!--',
            'css' => '/*',
            'sql' => '--',
            default => '//',
        };
    }

    private function peekLine(): string
    {
        $startPos = $this->position;
        $line = '';
        $pos = $startPos;
        
        while ($pos < $this->length && $this->input[$pos] !== "\n") {
            $line .= $this->input[$pos];
            $pos++;
        }
        
        return $line;
    }

    private function tokenizeDoctestAnnotation(): \Generator
    {
        $line = $this->consumeUntil("\n");
        
        if (preg_match('/@doctest\s+id[=:]\s*["\']?([^"\'\s]+)["\']?/', $line, $matches)) {
            yield new DoctestToken(DoctestTokenType::DoctestId, $line, $this->line, ['id' => $matches[1]]);
        } elseif (preg_match('/@doctest-region-start\s+name[=:]\s*["\']?([^"\'\s]+)["\']?/', $line, $matches)) {
            yield new DoctestToken(DoctestTokenType::DoctestRegionStart, $line, $this->line, ['name' => $matches[1]]);
        } elseif (str_contains($line, '@doctest-region-end')) {
            yield new DoctestToken(DoctestTokenType::DoctestRegionEnd, $line, $this->line);
        } else {
            // Generic doctest comment - treat as regular comment for now
            yield new DoctestToken(DoctestTokenType::Comment, $line, $this->line);
        }
    }

    private function tokenizeComment(): \Generator
    {
        $line = $this->consumeUntil("\n");
        yield new DoctestToken(DoctestTokenType::Comment, $line, $this->line);
    }

    private function tokenizeNewline(): \Generator
    {
        yield new DoctestToken(DoctestTokenType::Newline, "\n", $this->line);
        $this->position++;
        $this->line++;
    }

    private function tokenizeCode(): \Generator
    {
        $line = $this->consumeUntil("\n");
        if ($line !== '') {
            yield new DoctestToken(DoctestTokenType::Code, $line, $this->line);
        } else {
            // If no content was consumed, advance by one character to prevent infinite loop
            yield new DoctestToken(DoctestTokenType::Code, $this->input[$this->position], $this->line);
            $this->position++;
        }
    }

    private function consumeUntil(string $delimiters): string
    {
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