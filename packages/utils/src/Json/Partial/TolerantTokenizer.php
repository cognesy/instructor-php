<?php declare(strict_types=1);

namespace Cognesy\Utils\Json\Partial;

final class TolerantTokenizer
{
    private int $index = 0;

    public function __construct(private readonly string $input) {}

    public function index(): int { return $this->index; }

    public function next(): ?Token {
        $s = $this->input;
        $n = strlen($s);

        // skip whitespace
        while ($this->index < $n && ctype_space($s[$this->index])) $this->index++;
        if ($this->index >= $n) return null;

        $ch = $s[$this->index++];

        // structural tokens via single-char map
        $single = match ($ch) {
            '{' => TokenType::LeftBrace,
            '}' => TokenType::RightBrace,
            '[' => TokenType::LeftBracket,
            ']' => TokenType::RightBracket,
            ':' => TokenType::Colon,
            ',' => TokenType::Comma,
            default => null
        };
        if ($single !== null) return new Token($single);

        // strings (tolerant)
        if ($ch === '"') {
            $buf = '';
            $inBackticks = false;
            $backtickCount = 0;
            
            for (; ;) {
                if ($this->index >= $n) return new Token(TokenType::StringPartial, $buf);
                $c = $s[$this->index++];
                
                // Handle backticks - toggle literal mode on triple backticks
                if ($c === '`') {
                    $backtickCount++;
                    $buf .= $c;
                    if ($backtickCount === 3) {
                        $inBackticks = !$inBackticks;
                        $backtickCount = 0;
                    }
                    continue;
                } else {
                    $backtickCount = 0;
                }
                
                // If we're inside backticks, everything is literal
                if ($inBackticks) {
                    $buf .= $c;
                    continue;
                }
                
                // Normal string termination (only when not in backticks)
                if ($c === '"') return new Token(TokenType::String, $buf);
                
                // Normal escape handling (only when not in backticks)
                if ($c === '\\' && $this->index < $n) {
                    $esc = $s[$this->index++];
                    $buf .= match ($esc) {
                        '"', '\\', '/' => $esc,
                        'b' => "\x08",
                        'f' => "\x0C",
                        'n' => "\n",
                        'r' => "\r",
                        't' => "\t",
                        default => $esc,
                    };
                    continue;
                }
                $buf .= $c;
            }
        }

        // numbers (tolerant)
        if (preg_match('/[0-9\-]/', $ch)) {
            $start = $this->index - 1;
            while ($this->index < $n && preg_match('/[0-9eE\+\-\.]/', $s[$this->index])) $this->index++;
            $raw = substr($s, $start, $this->index - $start);
            $complete = ($this->index >= $n) || ctype_space($s[$this->index]) || strpbrk($s[$this->index], ',}]') !== false;
            return new Token($complete ? TokenType::Number : TokenType::NumberPartial, $raw);
        }

        // literals / bareword
        $start = $this->index - 1;
        while ($this->index < $n && ctype_alpha($s[$this->index])) $this->index++;
        $word = substr($s, $start, $this->index - $start);
        $lw = strtolower($word);

        // full matches
        if ($lw === 'true') return new Token(TokenType::True);
        if ($lw === 'false') return new Token(TokenType::False);
        if ($lw === 'null') return new Token(TokenType::Null);

        // partial-at-EOF tolerance
        $atEof = $this->index >= $n;
        if ($atEof) {
            if (str_starts_with('true', $lw)) return new Token(TokenType::True);
            if (str_starts_with('false', $lw)) return new Token(TokenType::False);
            if (str_starts_with('null', $lw)) return new Token(TokenType::Null);
        }

        // otherwise, bareword string
        return new Token(TokenType::String, $word);

//        // bareword → read until stopper
//        $start = $this->index - 1;
//        while ($this->index < $n && !ctype_space($s[$this->index]) && !strpbrk($s[$this->index], ',}]:')) $this->index++;
//        return new Token(TokenType::String, substr($s, $start, $this->index - $start));
    }
}
