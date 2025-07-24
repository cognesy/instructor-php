<?php

use Cognesy\Doctor\Markdown\Internal\Lexer;
use Cognesy\Doctor\Markdown\Internal\Parser;
use Cognesy\Doctor\Markdown\Internal\Token;
use Cognesy\Doctor\Markdown\Internal\TokenType;
use Cognesy\Doctor\Markdown\Nodes\CodeBlockNode;
use Cognesy\Doctor\Markdown\Nodes\ContentNode;
use Cognesy\Doctor\Markdown\Nodes\DocumentNode;
use Cognesy\Doctor\Markdown\Nodes\HeaderNode;
use Cognesy\Doctor\Markdown\Nodes\NewlineNode;

beforeEach(function () {
    $this->parser = new Parser();
    $this->lexer = new Lexer();
});

describe('Parser', function () {

    describe('Empty and basic parsing', function () {
        it('parses empty token array', function () {
            $document = DocumentNode::fromIterator($this->parser->parse([]));
            
            expect($document)->toBeInstanceOf(DocumentNode::class)
                ->and($document->children)->toBeEmpty();
        });

        it('parses simple content', function () {
            $tokens = [
                new Token(TokenType::Content, 'Hello world', 1)
            ];

            $document = DocumentNode::fromIterator($this->parser->parse($tokens));
            
            expect($document->children)->toHaveCount(1)
                ->and($document->children[0])->toBeInstanceOf(ContentNode::class)
                ->and($document->children[0]->content)->toBe('Hello world');
        });

        it('parses newline tokens', function () {
            $tokens = [
                new Token(TokenType::Content, 'Line 1', 1),
                new Token(TokenType::Newline, "\n", 1),
                new Token(TokenType::Content, 'Line 2', 2)
            ];
            
            $document = DocumentNode::fromIterator($this->parser->parse($tokens));
            
            expect($document->children)->toHaveCount(3)
                ->and($document->children[0])->toBeInstanceOf(ContentNode::class)
                ->and($document->children[1])->toBeInstanceOf(NewlineNode::class)
                ->and($document->children[2])->toBeInstanceOf(ContentNode::class);
        });
    });

    describe('Header parsing', function () {
        it('parses h1 header', function () {
            $tokens = [
                new Token(TokenType::Header, '# Main Title', 1)
            ];
            
            $document = DocumentNode::fromIterator($this->parser->parse($tokens));
            
            expect($document->children)->toHaveCount(1)
                ->and($document->children[0])->toBeInstanceOf(HeaderNode::class)
                ->and($document->children[0]->level)->toBe(1)
                ->and($document->children[0]->content)->toBe('Main Title');
        });

        it('parses h3 header', function () {
            $tokens = [
                new Token(TokenType::Header, '### Section Title', 1)
            ];
            
            $document = DocumentNode::fromIterator($this->parser->parse($tokens));
            $header = $document->children[0];
            
            expect($header)->toBeInstanceOf(HeaderNode::class)
                ->and($header->level)->toBe(3)
                ->and($header->content)->toBe('Section Title');
        });

        it('parses h6 header', function () {
            $tokens = [
                new Token(TokenType::Header, '###### Deep Section', 1)
            ];
            
            $document = DocumentNode::fromIterator($this->parser->parse($tokens));
            $header = $document->children[0];
            
            expect($header->level)->toBe(6)
                ->and($header->content)->toBe('Deep Section');
        });

        it('handles malformed header fallback', function () {
            $tokens = [
                new Token(TokenType::Header, '#InvalidHeader', 1)
            ];
            
            $document = DocumentNode::fromIterator($this->parser->parse($tokens));
            $header = $document->children[0];
            
            expect($header)->toBeInstanceOf(HeaderNode::class)
                ->and($header->level)->toBe(1)
                ->and($header->content)->toBe('InvalidHeader');
        });
    });

    describe('Code block parsing', function () {
        it('parses simple code block', function () {
            $tokens = [
                new Token(TokenType::CodeBlockFenceStart, '```', 1),
                new Token(TokenType::CodeBlockContent, "echo 'hello';", 2),
                new Token(TokenType::CodeBlockFenceEnd, '```', 3)
            ];
            
            $document = DocumentNode::fromIterator($this->parser->parse($tokens));
            $codeBlock = $document->children[0];
            
            expect($codeBlock)->toBeInstanceOf(CodeBlockNode::class)
                ->and($codeBlock->content)->toBe("echo 'hello';")
                ->and($codeBlock->language)->toBe('')
                ->and($codeBlock->metadata)->toBe([])
                ->and($codeBlock->id)->toMatch('/^[a-f0-9]{4}$/');
        });

        it('parses code block with language', function () {
            $tokens = [
                new Token(TokenType::CodeBlockFenceStart, '```', 1),
                new Token(TokenType::CodeBlockFenceInfo, 'php', 1),
                new Token(TokenType::CodeBlockContent, "echo 'hello';", 2),
                new Token(TokenType::CodeBlockFenceEnd, '```', 3)
            ];
            
            $document = DocumentNode::fromIterator($this->parser->parse($tokens));
            $codeBlock = $document->children[0];
            
            expect($codeBlock)->toBeInstanceOf(CodeBlockNode::class)
                ->and($codeBlock->language)->toBe('php')
                ->and($codeBlock->content)->toBe("echo 'hello';")
                ->and($codeBlock->metadata)->toBe([]);
        });

        it('parses code block with language and metadata', function () {
            $tokens = [
                new Token(TokenType::CodeBlockFenceStart, '```', 1),
                new Token(TokenType::CodeBlockFenceInfo, 'php marked=1 author=jclaude lastUpdate=2025-05-07@10:01:12', 1),
                new Token(TokenType::CodeBlockContent, "echo 'hello';", 2),
                new Token(TokenType::CodeBlockFenceEnd, '```', 3)
            ];
            
            $document = DocumentNode::fromIterator($this->parser->parse($tokens));
            $codeBlock = $document->children[0];
            
            expect($codeBlock->language)->toBe('php')
                ->and($codeBlock->metadata)->toBe([
                    'marked' => 1,  // Now correctly parsed as integer
                    'author' => 'jclaude',
                    'lastUpdate' => '2025-05-07@10:01:12'
                ]);
        });

        it('parses code block with metadata including id', function () {
            $tokens = [
                new Token(TokenType::CodeBlockFenceStart, '```', 1),
                new Token(TokenType::CodeBlockFenceInfo, 'javascript id=abc1 marked=1', 1),
                new Token(TokenType::CodeBlockContent, "console.log('test');", 2),
                new Token(TokenType::CodeBlockFenceEnd, '```', 3)
            ];
            
            $document = DocumentNode::fromIterator($this->parser->parse($tokens));
            $codeBlock = $document->children[0];
            
            expect($codeBlock->id)->toBe('abc1')
                ->and($codeBlock->language)->toBe('javascript')
                ->and($codeBlock->metadata)->toBe([
                    'id' => 'abc1',
                    'marked' => 1  // Now correctly parsed as integer
                ]);
        });

        it('extracts ID from @doctest annotation', function () {
            $tokens = [
                new Token(TokenType::CodeBlockFenceStart, '```', 1),
                new Token(TokenType::CodeBlockContent, "// @doctest id=\"def4\"\necho \"test\";", 2),
                new Token(TokenType::CodeBlockFenceEnd, '```', 3)
            ];
            
            $document = DocumentNode::fromIterator($this->parser->parse($tokens));
            $codeBlock = $document->children[0];
            
            expect($codeBlock->id)->toBe('def4');
        });

        it('throws exception when fence metadata and @doctest have conflicting keys', function () {
            $tokens = [
                new Token(TokenType::CodeBlockFenceStart, '```', 1),
                new Token(TokenType::CodeBlockFenceInfo, 'php id=abc1', 1),
                new Token(TokenType::CodeBlockContent, "// @doctest id=\"def4\"\necho \"test\";", 2),
                new Token(TokenType::CodeBlockFenceEnd, '```', 3)
            ];
            
            expect(function() use ($tokens) {
                DocumentNode::fromIterator($this->parser->parse($tokens));
            })->toThrow(\Cognesy\Doctor\Markdown\Exceptions\MetadataConflictException::class);
        });

        it('handles empty code block', function () {
            $tokens = [
                new Token(TokenType::CodeBlockFenceStart, '```', 1),
                new Token(TokenType::CodeBlockFenceEnd, '```', 2)
            ];
            
            $document = DocumentNode::fromIterator($this->parser->parse($tokens));
            
            expect($document->children)->toBeEmpty();
        });

        it('handles incomplete code block - missing content', function () {
            $tokens = [
                new Token(TokenType::CodeBlockFenceStart, '```', 1),
                new Token(TokenType::CodeBlockFenceEnd, '```', 2)
            ];
            
            $document = DocumentNode::fromIterator($this->parser->parse($tokens));
            
            expect($document->children)->toBeEmpty();
        });

        it('handles incomplete code block - missing end fence', function () {
            $tokens = [
                new Token(TokenType::CodeBlockFenceStart, '```', 1),
                new Token(TokenType::CodeBlockContent, "echo 'hello';", 2)
            ];
            
            $document = DocumentNode::fromIterator($this->parser->parse($tokens));
            
            expect($document->children)->toBeEmpty();
        });
    });

    describe('Unknown tokens', function () {
        it('ignores unknown token types', function () {
            $tokens = [
                new Token(TokenType::Content, 'Before', 1),
                // Simulating unknown token type would require extending enum, 
                // so we'll test with a content token instead
                new Token(TokenType::Content, 'After', 2)
            ];
            
            $document = DocumentNode::fromIterator($this->parser->parse($tokens));
            
            expect($document->children)->toHaveCount(2)
                ->and($document->children[0]->content)->toBe('Before')
                ->and($document->children[1]->content)->toBe('After');
        });
    });

    describe('Integration with Lexer', function () {
        it('parses complex markdown document end-to-end', function () {
            $input = "# Main Title\n\nSome content here.\n\n```php marked=1\necho 'Hello World';\n```\n\n## Subsection\n\nMore content.";
            
            $tokens = $this->lexer->tokenizeToArray($input);
            $document = DocumentNode::fromIterator($this->parser->parse($tokens));
            
            // Should have: Header, Newline, Newline, Content, Newline, Newline, CodeBlock, Newline, Newline, Header, Newline, Newline, Content
            expect($document->children)->toHaveCount(13);
            
            // Check specific nodes
            expect($document->children[0])->toBeInstanceOf(HeaderNode::class);
            expect($document->children[0]->level)->toBe(1);
            expect($document->children[0]->content)->toBe('Main Title');
            
            expect($document->children[3])->toBeInstanceOf(ContentNode::class);
            expect($document->children[3]->content)->toBe('Some content here.');
            
            expect($document->children[6])->toBeInstanceOf(CodeBlockNode::class);
            expect($document->children[6]->language)->toBe('php');
            expect($document->children[6]->metadata)->toBe(['marked' => 1]);  // Now correctly parsed as integer
            expect($document->children[6]->content)->toBe("echo 'Hello World';");
            
            expect($document->children[9])->toBeInstanceOf(HeaderNode::class);
            expect($document->children[9]->level)->toBe(2);
            expect($document->children[9]->content)->toBe('Subsection');
        });

        it('handles multiple code blocks in document', function () {
            $input = "```php\necho 'first';\n```\n\n```javascript id=abc1\nconsole.log('second');\n```";
            
            $tokens = $this->lexer->tokenizeToArray($input);
            $document = DocumentNode::fromIterator($this->parser->parse($tokens));
            
            $codeBlocks = array_filter($document->children, fn($node) => $node instanceof CodeBlockNode);
            
            expect($codeBlocks)->toHaveCount(2);
            
            $firstBlock = array_values($codeBlocks)[0];
            $secondBlock = array_values($codeBlocks)[1];
            
            expect($firstBlock->language)->toBe('php')
                ->and($firstBlock->content)->toBe("echo 'first';");
            
            expect($secondBlock->language)->toBe('javascript')
                ->and($secondBlock->id)->toBe('abc1')
                ->and($secondBlock->content)->toBe("console.log('second');");
        });
    });

    describe('Fence info parsing', function () {
        it('parses language only', function () {
            $tokens = [
                new Token(TokenType::CodeBlockFenceStart, '```', 1),
                new Token(TokenType::CodeBlockFenceInfo, 'python', 1),
                new Token(TokenType::CodeBlockContent, "print('hello')", 2),
                new Token(TokenType::CodeBlockFenceEnd, '```', 3)
            ];
            
            $document = DocumentNode::fromIterator($this->parser->parse($tokens));
            $codeBlock = $document->children[0];
            
            expect($codeBlock->language)->toBe('python')
                ->and($codeBlock->metadata)->toBe([]);
        });

        it('parses complex metadata with various formats', function () {
            $tokens = [
                new Token(TokenType::CodeBlockFenceStart, '```', 1),
                new Token(TokenType::CodeBlockFenceInfo, 'bash title="My Script" version=1.2.3 debug=true', 1),
                new Token(TokenType::CodeBlockContent, "#!/bin/bash\necho 'test'", 2),
                new Token(TokenType::CodeBlockFenceEnd, '```', 3)
            ];
            
            $document = DocumentNode::fromIterator($this->parser->parse($tokens));
            $codeBlock = $document->children[0];
            
            expect($codeBlock->language)->toBe('bash')
                ->and($codeBlock->metadata)->toBe([
                    'title' => 'My Script',  // Quotes are properly removed
                    'version' => '1.2.3',    // Parsed as string (has 3 parts)
                    'debug' => true          // Parsed as boolean
                ]);
        });

        it('handles whitespace in fence info', function () {
            $tokens = [
                new Token(TokenType::CodeBlockFenceStart, '```', 1),
                new Token(TokenType::CodeBlockFenceInfo, '   ruby   author=jane   date=2024-01-01   ', 1),
                new Token(TokenType::CodeBlockContent, "puts 'hello'", 2),
                new Token(TokenType::CodeBlockFenceEnd, '```', 3)
            ];
            
            $document = DocumentNode::fromIterator($this->parser->parse($tokens));
            $codeBlock = $document->children[0];
            
            expect($codeBlock->language)->toBe('ruby')
                ->and($codeBlock->metadata)->toBe([
                    'author' => 'jane',
                    'date' => '2024-01-01'
                ]);
        });

        it('parses array parameters in fence info', function () {
            $tokens = [
                new Token(TokenType::CodeBlockFenceStart, '```', 1),
                new Token(TokenType::CodeBlockFenceInfo, 'python retries=[1, 2, 3] tags=["test", "demo"]', 1),
                new Token(TokenType::CodeBlockContent, "print('hello')", 2),
                new Token(TokenType::CodeBlockFenceEnd, '```', 3)
            ];
            
            $document = DocumentNode::fromIterator($this->parser->parse($tokens));
            $codeBlock = $document->children[0];
            
            expect($codeBlock->language)->toBe('python')
                ->and($codeBlock->metadata)->toBe([
                    'retries' => [1, 2, 3],
                    'tags' => ['test', 'demo']
                ]);
        });

        it('parses @doctest annotations in code content', function () {
            $tokens = [
                new Token(TokenType::CodeBlockFenceStart, '```', 1),
                new Token(TokenType::CodeBlockFenceInfo, 'php', 1),
                new Token(TokenType::CodeBlockContent, "// @doctest timeout=5000 id=\"test123\"\necho \"test\";", 2),
                new Token(TokenType::CodeBlockFenceEnd, '```', 3)
            ];
            
            $document = DocumentNode::fromIterator($this->parser->parse($tokens));
            $codeBlock = $document->children[0];
            
            expect($codeBlock->language)->toBe('php')
                ->and($codeBlock->id)->toBe('test123')
                ->and($codeBlock->metadata)->toBe([
                    'timeout' => 5000,
                    'id' => 'test123'
                ]);
        });

        it('combines fence metadata and @doctest metadata without conflicts', function () {
            $tokens = [
                new Token(TokenType::CodeBlockFenceStart, '```', 1),
                new Token(TokenType::CodeBlockFenceInfo, 'php timeout=1000 debug=true', 1),
                new Token(TokenType::CodeBlockContent, "// @doctest id=\"test123\" verbose=false\necho \"test\";", 2),
                new Token(TokenType::CodeBlockFenceEnd, '```', 3)
            ];
            
            $document = DocumentNode::fromIterator($this->parser->parse($tokens));
            $codeBlock = $document->children[0];
            
            expect($codeBlock->language)->toBe('php')
                ->and($codeBlock->id)->toBe('test123')
                ->and($codeBlock->metadata)->toBe([
                    'timeout' => 1000,   // from fence
                    'debug' => true,     // from fence
                    'id' => 'test123',   // from @doctest
                    'verbose' => false   // from @doctest
                ]);
        });

        it('handles different comment styles for @doctest', function () {
            $tokens = [
                new Token(TokenType::CodeBlockFenceStart, '```', 1),
                new Token(TokenType::CodeBlockFenceInfo, 'python', 1),
                new Token(TokenType::CodeBlockContent, "# @doctest param=\"python_test\"\nprint(\"hello\")", 2),
                new Token(TokenType::CodeBlockFenceEnd, '```', 3)
            ];
            
            $document = DocumentNode::fromIterator($this->parser->parse($tokens));
            $codeBlock = $document->children[0];
            
            expect($codeBlock->language)->toBe('python')
                ->and($codeBlock->metadata)->toBe([
                    'param' => 'python_test'
                ]);
        });

        it('uses extracted ID when no id in fence or @doctest metadata', function () {
            $tokens = [
                new Token(TokenType::CodeBlockFenceStart, '```', 1),
                new Token(TokenType::CodeBlockFenceInfo, 'php debug=true', 1),
                new Token(TokenType::CodeBlockContent, "// @doctest id=\"extracted123\" timeout=5000\necho \"test\";", 2),
                new Token(TokenType::CodeBlockFenceEnd, '```', 3)
            ];
            
            $document = DocumentNode::fromIterator($this->parser->parse($tokens));
            $codeBlock = $document->children[0];
            
            expect($codeBlock->language)->toBe('php')
                ->and($codeBlock->id)->toBe('extracted123')
                ->and($codeBlock->metadata)->toBe([
                    'debug' => true,         // from fence
                    'id' => 'extracted123',  // from @doctest
                    'timeout' => 5000       // from @doctest
                ]);
        });

        it('throws exception for conflicting non-id metadata keys', function () {
            $tokens = [
                new Token(TokenType::CodeBlockFenceStart, '```', 1),
                new Token(TokenType::CodeBlockFenceInfo, 'php timeout=1000', 1),
                new Token(TokenType::CodeBlockContent, "// @doctest timeout=2000\necho \"test\";", 2),
                new Token(TokenType::CodeBlockFenceEnd, '```', 3)
            ];
            
            expect(function() use ($tokens) {
                DocumentNode::fromIterator($this->parser->parse($tokens));
            })->toThrow(\Cognesy\Doctor\Markdown\Exceptions\MetadataConflictException::class);
        });
    });
});

