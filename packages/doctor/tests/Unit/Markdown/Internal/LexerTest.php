<?php

use Cognesy\Doctor\Markdown\Internal\Lexer;
use Cognesy\Doctor\Markdown\Internal\TokenType;

beforeEach(function () {
    $this->lexer = new Lexer();
});

describe('Lexer', function () {
    describe('Basic tokenization', function () {
        it('tokenizes empty string', function () {
            $tokens = $this->lexer->tokenizeToArray('');
            expect($tokens)->toBeEmpty();
        });

        it('tokenizes simple content', function () {
            $tokens = $this->lexer->tokenizeToArray('Hello world');
            
            expect($tokens)->toHaveCount(1)
                ->and($tokens[0]->type)->toBe(TokenType::Content)
                ->and($tokens[0]->value)->toBe('Hello world')
                ->and($tokens[0]->line)->toBe(1);
        });

        it('tokenizes newline characters', function () {
            $tokens = $this->lexer->tokenizeToArray("Line 1\nLine 2");
            
            expect($tokens)->toHaveCount(3)
                ->and($tokens[0]->type)->toBe(TokenType::Content)
                ->and($tokens[0]->value)->toBe('Line 1')
                ->and($tokens[1]->type)->toBe(TokenType::Newline)
                ->and($tokens[1]->value)->toBe("\n")
                ->and($tokens[2]->type)->toBe(TokenType::Content)
                ->and($tokens[2]->value)->toBe('Line 2');
        });
    });

    describe('Header tokenization', function () {
        it('tokenizes h1 header', function () {
            $tokens = $this->lexer->tokenizeToArray('# Main Title');
            
            expect($tokens)->toHaveCount(1)
                ->and($tokens[0]->type)->toBe(TokenType::Header)
                ->and($tokens[0]->value)->toBe('# Main Title')
                ->and($tokens[0]->line)->toBe(1);
        });

        it('tokenizes h3 header', function () {
            $tokens = $this->lexer->tokenizeToArray('### Section Title');
            
            expect($tokens)->toHaveCount(1)
                ->and($tokens[0]->type)->toBe(TokenType::Header)
                ->and($tokens[0]->value)->toBe('### Section Title')
                ->and($tokens[0]->line)->toBe(1);
        });

        it('requires space after hash for header', function () {
            $tokens = $this->lexer->tokenizeToArray('#NotAHeader');
            
            expect($tokens)->toHaveCount(1)
                ->and($tokens[0]->type)->toBe(TokenType::Content)
                ->and($tokens[0]->value)->toBe('#NotAHeader');
        });

        it('handles headers with trailing content', function () {
            $tokens = $this->lexer->tokenizeToArray("# Title\nContent");
            
            expect($tokens)->toHaveCount(3)
                ->and($tokens[0]->type)->toBe(TokenType::Header)
                ->and($tokens[0]->value)->toBe('# Title')
                ->and($tokens[1]->type)->toBe(TokenType::Newline)
                ->and($tokens[2]->type)->toBe(TokenType::Content)
                ->and($tokens[2]->value)->toBe('Content');
        });
    });

    describe('Code block tokenization', function () {
        it('tokenizes simple code block', function () {
            $input = "```\necho 'hello';\n```";
            $tokens = $this->lexer->tokenizeToArray($input);
            
            expect($tokens)->toHaveCount(3)
                ->and($tokens[0]->type)->toBe(TokenType::CodeBlockFenceStart)
                ->and($tokens[0]->value)->toBe('```')
                ->and($tokens[1]->type)->toBe(TokenType::CodeBlockContent)
                ->and($tokens[1]->value)->toBe("echo 'hello';")
                ->and($tokens[2]->type)->toBe(TokenType::CodeBlockFenceEnd)
                ->and($tokens[2]->value)->toBe('```');
        });

        it('tokenizes code block with language', function () {
            $input = "```php\necho 'hello';\n```";
            $tokens = $this->lexer->tokenizeToArray($input);
            
            expect($tokens)->toHaveCount(4)
                ->and($tokens[0]->type)->toBe(TokenType::CodeBlockFenceStart)
                ->and($tokens[0]->value)->toBe('```')
                ->and($tokens[1]->type)->toBe(TokenType::CodeBlockFenceInfo)
                ->and($tokens[1]->value)->toBe('php')
                ->and($tokens[2]->type)->toBe(TokenType::CodeBlockContent)
                ->and($tokens[2]->value)->toBe("echo 'hello';")
                ->and($tokens[3]->type)->toBe(TokenType::CodeBlockFenceEnd)
                ->and($tokens[3]->value)->toBe('```');
        });

        it('tokenizes code block with metadata', function () {
            $input = "```php marked=1 author=jclaude\necho 'hello';\n```";
            $tokens = $this->lexer->tokenizeToArray($input);
            
            expect($tokens)->toHaveCount(4)
                ->and($tokens[0]->type)->toBe(TokenType::CodeBlockFenceStart)
                ->and($tokens[1]->type)->toBe(TokenType::CodeBlockFenceInfo)
                ->and($tokens[1]->value)->toBe('php marked=1 author=jclaude')
                ->and($tokens[2]->type)->toBe(TokenType::CodeBlockContent)
                ->and($tokens[3]->type)->toBe(TokenType::CodeBlockFenceEnd);
        });

        it('handles empty code block', function () {
            $input = "```\n```";
            $tokens = $this->lexer->tokenizeToArray($input);
            
            expect($tokens)->toHaveCount(3)
                ->and($tokens[0]->type)->toBe(TokenType::CodeBlockFenceStart)
                ->and($tokens[1]->type)->toBe(TokenType::CodeBlockContent)
                ->and($tokens[1]->value)->toBe('')
                ->and($tokens[2]->type)->toBe(TokenType::CodeBlockFenceEnd);
        });

        it('handles multiline code content', function () {
            $input = "```\nline 1\nline 2\nline 3\n```";
            $tokens = $this->lexer->tokenizeToArray($input);
            
            expect($tokens)->toHaveCount(3)
                ->and($tokens[0]->type)->toBe(TokenType::CodeBlockFenceStart)
                ->and($tokens[1]->type)->toBe(TokenType::CodeBlockContent)
                ->and($tokens[1]->value)->toBe("line 1\nline 2\nline 3")
                ->and($tokens[2]->type)->toBe(TokenType::CodeBlockFenceEnd);
        });

        it('handles unclosed code block', function () {
            $input = "```\necho 'hello';\nno closing fence";
            $tokens = $this->lexer->tokenizeToArray($input);
            
            expect($tokens)->toHaveCount(2)
                ->and($tokens[0]->type)->toBe(TokenType::CodeBlockFenceStart)
                ->and($tokens[1]->type)->toBe(TokenType::CodeBlockContent)
                ->and($tokens[1]->value)->toBe("echo 'hello';\nno closing fence");
        });
    });

    describe('Mixed content tokenization', function () {
        it('tokenizes complex markdown document', function () {
            $input = "# Title\n\nSome content\n\n```php\necho 'test';\n```\n\nMore content";
            $tokens = $this->lexer->tokenizeToArray($input);
            
            expect($tokens)->toHaveCount(13);
            
            // Check token sequence
            expect($tokens[0]->type)->toBe(TokenType::Header);
            expect($tokens[1]->type)->toBe(TokenType::Newline);
            expect($tokens[2]->type)->toBe(TokenType::Newline);
            expect($tokens[3]->type)->toBe(TokenType::Content);
            expect($tokens[4]->type)->toBe(TokenType::Newline);
            expect($tokens[5]->type)->toBe(TokenType::Newline);
            expect($tokens[6]->type)->toBe(TokenType::CodeBlockFenceStart);
            expect($tokens[7]->type)->toBe(TokenType::CodeBlockFenceInfo);
            expect($tokens[8]->type)->toBe(TokenType::CodeBlockContent);
            expect($tokens[9]->type)->toBe(TokenType::CodeBlockFenceEnd);
            expect($tokens[10]->type)->toBe(TokenType::Newline);
            expect($tokens[11]->type)->toBe(TokenType::Newline);
            expect($tokens[12]->type)->toBe(TokenType::Content);
        });

        it('handles backticks in content', function () {
            $input = "Use `inline code` in text";
            $tokens = $this->lexer->tokenizeToArray($input);
            
            expect($tokens)->toHaveCount(1)
                ->and($tokens[0]->type)->toBe(TokenType::Content)
                ->and($tokens[0]->value)->toBe("Use `inline code` in text");
        });
    });

    describe('Line tracking', function () {
        it('tracks line numbers correctly', function () {
            $input = "Line 1\nLine 2\n# Header\nLine 4";
            $tokens = $this->lexer->tokenizeToArray($input);
            
            expect($tokens[0]->line)->toBe(1) // Line 1
                ->and($tokens[1]->line)->toBe(1) // \n
                ->and($tokens[2]->line)->toBe(2) // Line 2
                ->and($tokens[3]->line)->toBe(2) // \n
                ->and($tokens[4]->line)->toBe(3) // # Header
                ->and($tokens[5]->line)->toBe(3) // \n
                ->and($tokens[6]->line)->toBe(4); // Line 4
        });

        it('tracks line numbers in code blocks', function () {
            $input = "```\nline 1\nline 2\n```";
            $tokens = $this->lexer->tokenizeToArray($input);
            
            expect($tokens[0]->line)->toBe(1) // ```
                ->and($tokens[1]->line)->toBe(2) // code content starts line 2
                ->and($tokens[2]->line)->toBe(4); // ``` at line 4
        });
    });

    describe('Edge cases', function () {
        it('handles only whitespace', function () {
            $tokens = $this->lexer->tokenizeToArray("   \t  ");
            
            expect($tokens)->toHaveCount(1)
                ->and($tokens[0]->type)->toBe(TokenType::Content)
                ->and($tokens[0]->value)->toBe("   \t  ");
        });

        it('handles multiple consecutive newlines', function () {
            $tokens = $this->lexer->tokenizeToArray("\n\n\n");
            
            expect($tokens)->toHaveCount(3)
                ->and($tokens[0]->type)->toBe(TokenType::Newline)
                ->and($tokens[1]->type)->toBe(TokenType::Newline)
                ->and($tokens[2]->type)->toBe(TokenType::Newline);
        });

        it('handles text ending with newline', function () {
            $tokens = $this->lexer->tokenizeToArray("Hello\n");
            
            expect($tokens)->toHaveCount(2)
                ->and($tokens[0]->type)->toBe(TokenType::Content)
                ->and($tokens[0]->value)->toBe('Hello')
                ->and($tokens[1]->type)->toBe(TokenType::Newline);
        });
    });

    describe('Line ending normalization', function () {
        it('normalizes Windows CRLF line endings', function () {
            $input = "# Header\r\n\r\nContent\r\n";
            $tokens = $this->lexer->tokenizeToArray($input);
            
            expect($tokens)->toHaveCount(5)
                ->and($tokens[0]->type)->toBe(TokenType::Header)
                ->and($tokens[0]->value)->toBe('# Header')
                ->and($tokens[1]->type)->toBe(TokenType::Newline)
                ->and($tokens[2]->type)->toBe(TokenType::Newline)
                ->and($tokens[3]->type)->toBe(TokenType::Content)
                ->and($tokens[3]->value)->toBe('Content')
                ->and($tokens[4]->type)->toBe(TokenType::Newline);
        });

        it('normalizes old Mac CR line endings', function () {
            $input = "# Header\r\rContent\r";
            $tokens = $this->lexer->tokenizeToArray($input);
            
            expect($tokens)->toHaveCount(5)
                ->and($tokens[0]->type)->toBe(TokenType::Header)
                ->and($tokens[0]->value)->toBe('# Header')
                ->and($tokens[1]->type)->toBe(TokenType::Newline)
                ->and($tokens[2]->type)->toBe(TokenType::Newline)
                ->and($tokens[3]->type)->toBe(TokenType::Content)
                ->and($tokens[3]->value)->toBe('Content')
                ->and($tokens[4]->type)->toBe(TokenType::Newline);
        });

        it('normalizes mixed line endings', function () {
            $input = "# Header\r\n\nContent\r";
            $tokens = $this->lexer->tokenizeToArray($input);
            
            expect($tokens)->toHaveCount(5)
                ->and($tokens[0]->type)->toBe(TokenType::Header)
                ->and($tokens[0]->value)->toBe('# Header')
                ->and($tokens[1]->type)->toBe(TokenType::Newline)
                ->and($tokens[2]->type)->toBe(TokenType::Newline)
                ->and($tokens[3]->type)->toBe(TokenType::Content)
                ->and($tokens[3]->value)->toBe('Content')
                ->and($tokens[4]->type)->toBe(TokenType::Newline);
        });

        it('handles code blocks with different line endings', function () {
            $input = "```php\r\necho 'test';\r\n```";
            $tokens = $this->lexer->tokenizeToArray($input);
            
            expect($tokens)->toHaveCount(4)
                ->and($tokens[0]->type)->toBe(TokenType::CodeBlockFenceStart)
                ->and($tokens[1]->type)->toBe(TokenType::CodeBlockFenceInfo)
                ->and($tokens[1]->value)->toBe('php')
                ->and($tokens[2]->type)->toBe(TokenType::CodeBlockContent)
                ->and($tokens[2]->value)->toBe("echo 'test';")
                ->and($tokens[3]->type)->toBe(TokenType::CodeBlockFenceEnd);
        });

        it('preserves newlines in code block content with different line endings', function () {
            $input = "```\r\nline1\r\nline2\r\n```";
            $tokens = $this->lexer->tokenizeToArray($input);
            
            expect($tokens)->toHaveCount(3)
                ->and($tokens[0]->type)->toBe(TokenType::CodeBlockFenceStart)
                ->and($tokens[1]->type)->toBe(TokenType::CodeBlockContent)
                ->and($tokens[1]->value)->toBe("line1\nline2")
                ->and($tokens[2]->type)->toBe(TokenType::CodeBlockFenceEnd);
        });

        it('handles empty code blocks with different line endings', function () {
            $input = "```\r\n```";
            $tokens = $this->lexer->tokenizeToArray($input);
            
            expect($tokens)->toHaveCount(3)
                ->and($tokens[0]->type)->toBe(TokenType::CodeBlockFenceStart)
                ->and($tokens[1]->type)->toBe(TokenType::CodeBlockContent)
                ->and($tokens[1]->value)->toBe('')
                ->and($tokens[2]->type)->toBe(TokenType::CodeBlockFenceEnd);
        });
    });
});
