<?php

use Cognesy\Doctor\Markdown\Internal\CodeBlockIdentifier;
use Cognesy\Doctor\Markdown\MarkdownFile;

describe('MarkdownFile Feature Tests', function () {
    describe('End-to-end parsing', function () {
        it('parses complete markdown document', function () {
            $input = <<<'MARKDOWN'
            # Main Documentation

            This is a sample document with various elements.

            ## Code Examples

            Here's a PHP example:

            ```php marked=1 author=jane
            <?php
            echo "Hello, World!";
            // @doctest id="abc1"
            ?>
            ```

            And here's a JavaScript example:

            ```javascript version='2.0'
            console.log('Hello from JS');
            ```

            ### Subsection

            More content here.

            ```
            Plain code block without language
            ```
            MARKDOWN;

            $markdownFile = MarkdownFile::fromString($input);

            expect($markdownFile)->toBeInstanceOf(MarkdownFile::class);

            // Test headers
            $headers = iterator_to_array($markdownFile->headers());
            expect($headers)->toHaveCount(3)
                ->and($headers[0]->level)->toBe(1)
                ->and($headers[0]->content)->toBe('Main Documentation')
                ->and($headers[1]->level)->toBe(2)
                ->and($headers[1]->content)->toBe('Code Examples')
                ->and($headers[2]->level)->toBe(3)
                ->and($headers[2]->content)->toBe('Subsection');

            // Test code blocks
            $codeblocks = iterator_to_array($markdownFile->codeBlocks());
            expect($codeblocks)->toHaveCount(3);

            $phpBlock = $codeblocks[0];
            expect($phpBlock->language)->toBe('php')
                ->and($phpBlock->metadata)->toBe(['marked' => 1, 'author' => 'jane', 'id' => 'abc1'])  // includes extracted ID
                ->and($phpBlock->id)->toBe('abc1')
                ->and($phpBlock->content)->toContain('echo "Hello, World!"')
                ->and($phpBlock->content)->toContain('@doctest id="abc1"');

            $jsBlock = $codeblocks[1];
            expect($jsBlock->language)->toBe('javascript')
                ->and($jsBlock->metadata)->toBe(['version' => '2.0'])
                ->and($jsBlock->content)->toBe("console.log('Hello from JS');");

            $plainBlock = $codeblocks[2];
            expect($plainBlock->language)->toBe('')
                ->and($plainBlock->metadata)->toBe([])
                ->and($plainBlock->content)->toBe('Plain code block without language');
        });

        it('handles document with only headers', function () {
            $input = <<<'MARKDOWN'
            # Title
            ## Section
            ### Subsection
            #### Deep Section
            MARKDOWN;

            $markdownFile = MarkdownFile::fromString($input);
            $headers = iterator_to_array($markdownFile->headers());

            expect($headers)->toHaveCount(4)
                ->and($headers[0]->level)->toBe(1)
                ->and($headers[1]->level)->toBe(2)
                ->and($headers[2]->level)->toBe(3)
                ->and($headers[3]->level)->toBe(4);

            expect(iterator_to_array($markdownFile->codeBlocks()))->toBeEmpty();
        });

        it('handles document with only code blocks', function () {
            $input = <<<'MARKDOWN'
            ```php
            echo "First block";
            ```

            ```javascript id=test1
            console.log("Second block");
            ```

            ```python author=bob date=2024-01-01
            print("Third block")
            ```
            MARKDOWN;

            $markdownFile = MarkdownFile::fromString($input);
            $codeblocks = iterator_to_array($markdownFile->codeBlocks());

            expect($codeblocks)->toHaveCount(3);
            expect(iterator_to_array($markdownFile->headers()))->toBeEmpty();

            expect($codeblocks[0]->language)->toBe('php');
            expect($codeblocks[1]->language)->toBe('javascript')
                ->and($codeblocks[1]->id)->toBe('test1');
            expect($codeblocks[2]->language)->toBe('python')
                ->and($codeblocks[2]->metadata)->toBe(['author' => 'bob', 'date' => '2024-01-01']);
        });

        it('handles empty document', function () {
            $markdownFile = MarkdownFile::fromString('');

            expect(iterator_to_array($markdownFile->headers()))->toBeEmpty();
            expect(iterator_to_array($markdownFile->codeBlocks()))->toBeEmpty();
            expect($markdownFile->toString())->toBe('');
        });
    });

    describe('Code block ID generation and extraction', function () {
        it('generates unique IDs for code blocks without IDs', function () {
            $input = <<<'MARKDOWN'
            ```php
            echo "test1";
            ```

            ```javascript
            console.log("test2");
            ```
            MARKDOWN;

            $markdownFile = MarkdownFile::fromString($input);
            $codeblocks = iterator_to_array($markdownFile->codeBlocks());

            expect($codeblocks)->toHaveCount(2);
            expect(CodeBlockIdentifier::isValid($codeblocks[0]->id))->toBeTrue()
                ->and(CodeBlockIdentifier::isValid($codeblocks[1]->id))->toBeTrue();

            // IDs should be different
            expect($codeblocks[0]->id)->not->toBe($codeblocks[1]->id);
        });

        it('respects IDs in @doctest annotations', function () {
            $input = <<<'MARKDOWN'
            ```php
            <?php
            // @doctest id="test"
            echo "Hello";
            ?>
            ```
            MARKDOWN;

            $markdownFile = MarkdownFile::fromString($input);
            $codeblocks = iterator_to_array($markdownFile->codeBlocks());

            expect($codeblocks[0]->id)->toBe('test');
        });

        it('throws exception for conflicting fence metadata and @doctest ID', function () {
            $input = <<<'MARKDOWN'
            ```php id=fence
            <?php
            // @doctest id="comm"
            echo "Hello";
            ?>
            ```
            MARKDOWN;

            expect(function() use ($input) {
                MarkdownFile::fromString($input);
            })->toThrow(\Cognesy\Doctor\Markdown\Exceptions\MetadataConflictException::class);
        });

        it('combines fence metadata and @doctest metadata without conflicts', function () {
            $input = <<<'MARKDOWN'
            ```php timeout=1000
            <?php
            // @doctest id="test123"
            echo "Hello";
            ?>
            ```
            MARKDOWN;

            $markdownFile = MarkdownFile::fromString($input);
            $codeblocks = iterator_to_array($markdownFile->codeBlocks());

            // Should combine metadata from both sources
            expect($codeblocks[0]->id)->toBe('test123')
                ->and($codeblocks[0]->metadata)->toBe(['timeout' => 1000, 'id' => 'test123']);
        });
    });

    describe('toString conversion', function () {
        it('reconstructs markdown from parsed document', function () {
            $input = <<<'MARKDOWN'
            # Title

            Content paragraph.

            ```php
            echo "test";
            ```

            ## Section
            MARKDOWN;

            $markdownFile = MarkdownFile::fromString($input);
            $output = $markdownFile->toString();

            expect($output)->toContain('# Title')
                ->and($output)->toContain('Content paragraph.')
                ->and($output)->toContain('```')
                ->and($output)->toContain('echo "test";')
                ->and($output)->toContain('## Section');
        });

        it('renders metadata with comments style by default', function () {
            $input = <<<'MARKDOWN'
            ```php timeout=1000 debug=true
            // @doctest id="test123" verbose=false
            echo "Hello World";
            ```
            MARKDOWN;

            $markdownFile = MarkdownFile::fromString($input);
            $output = $markdownFile->toString(\Cognesy\Doctor\Markdown\Enums\MetadataStyle::Comments);

            expect($output)->toContain('```php')
                ->and($output)->toContain('// @doctest timeout=1000 debug=true id="test123" verbose=false')
                ->and($output)->toContain('echo "Hello World";')
                ->and($output)->not->toContain('```php timeout=1000 debug=true') // Should not have metadata in fence line
                ->and($output)->not->toContain('// @doctest id="test123" verbose=false'); // Should not have original line
        });

        it('renders metadata with fence style', function () {
            $input = <<<'MARKDOWN'
            ```php timeout=1000 debug=true
            // @doctest id="test123" verbose=false
            echo "Hello World";
            ```
            MARKDOWN;

            $markdownFile = MarkdownFile::fromString($input);
            $output = $markdownFile->toString(\Cognesy\Doctor\Markdown\Enums\MetadataStyle::Fence);

            expect($output)->toContain('```php timeout=1000 debug=true verbose=false') // Metadata in fence line
                ->and($output)->toContain('// @doctest id="test123"') // Only ID in comment
                ->and($output)->toContain('echo "Hello World";')
                ->and($output)->not->toContain('// @doctest id="test123" verbose=false'); // Should not have original line
        });

        it('handles boolean, array, and string values correctly in fence style', function () {
            $input = <<<'MARKDOWN'
            ```php timeout=5000 debug=true tags=["test", "demo"] name="example"
            echo "test";
            ```
            MARKDOWN;

            $markdownFile = MarkdownFile::fromString($input);
            $output = $markdownFile->toString(\Cognesy\Doctor\Markdown\Enums\MetadataStyle::Fence);

            expect($output)->toContain('```php timeout=5000 debug=true tags=["test", "demo"] name="example"')
                ->and($output)->toContain('echo "test";');
        });

        it('handles boolean, array, and string values correctly in comments style', function () {
            $input = <<<'MARKDOWN'
            ```php timeout=5000 debug=true tags=["test", "demo"] name="example"
            echo "test";
            ```
            MARKDOWN;

            $markdownFile = MarkdownFile::fromString($input);
            $output = $markdownFile->toString(\Cognesy\Doctor\Markdown\Enums\MetadataStyle::Comments);

            expect($output)->toContain('```php')
                ->and($output)->toContain('// @doctest timeout=5000 debug=true tags=["test", "demo"] name="example"')
                ->and($output)->toContain('echo "test";');
        });

        it('preserves language in code blocks', function () {
            $input = <<<'MARKDOWN'
            ```php
            echo "test";
            ```
            MARKDOWN;

            $markdownFile = MarkdownFile::fromString($input);
            $output = $markdownFile->toString();

            expect($output)->toContain('```php');
        });

        it('handles empty code blocks', function () {
            $input = <<<'MARKDOWN'
            ```
            ```
            MARKDOWN;

            $markdownFile = MarkdownFile::fromString($input);
            $output = $markdownFile->toString();

            expect($output)->toContain('```')
                ->and($output)->not->toContain('undefined');
        });
    });

    describe('Metadata handling', function () {
        it('handles front matter metadata', function () {
            $input = <<<'MARKDOWN'
            ---
            title: "Test Document"
            author: "John Doe"
            version: 1.0
            ---

            # Content

            This is the content.
            MARKDOWN;

            $markdownFile = MarkdownFile::fromString($input);

            expect($markdownFile->hasMetadata('title'))->toBeTrue()
                ->and($markdownFile->metadata('title'))->toBe('Test Document')
                ->and($markdownFile->metadata('author'))->toBe('John Doe')
                ->and($markdownFile->metadata('version'))->toBe(1.0)
                ->and($markdownFile->metadata('nonexistent', 'default'))->toBe('default');
        });

        it('can add metadata programmatically', function () {
            $markdownFile = MarkdownFile::fromString('# Test');
            $updated = $markdownFile->withMetadata('custom', 'value');

            expect($updated->hasMetadata('custom'))->toBeTrue()
                ->and($updated->metadata('custom'))->toBe('value');
        });
    });

    describe('Path handling', function () {
        it('stores and retrieves path', function () {
            $path = '/path/to/document.md';
            $markdownFile = MarkdownFile::fromString('# Test', $path);

            expect($markdownFile->path())->toBe($path);
        });

        it('can update path', function () {
            $markdownFile = MarkdownFile::fromString('# Test', '/old/path.md');
            $updated = $markdownFile->withPath('/new/path.md');

            expect($updated->path())->toBe('/new/path.md');
        });
    });

    describe('Complex real-world scenarios', function () {
        it('handles documentation with mixed content types', function () {
            $input = <<<'MARKDOWN'
            # API Documentation

            Welcome to our API documentation.

            ## Authentication

            Use the following PHP code to authenticate:

            ```php marked=true secure=high
            <?php
            $token = authenticate_user($username, $password);
            // @doctest id="auth"
            ?>
            ```

            ## Examples

            ### GET Request

            ```javascript example=get
            fetch('/api/users')
              .then(response => response.json())
              .then(data => console.log(data));
            ```

            ### POST Request

            ```javascript example=post id=post1
            fetch('/api/users', {
              method: 'POST',
              body: JSON.stringify({name: 'John'})
            });
            ```

            ## Error Handling

            Common errors:

            ```json
            {
              "error": "Invalid credentials",
              "code": 401
            }
            ```

            ## Conclusion

            That's all for now!
            MARKDOWN;

            $markdownFile = MarkdownFile::fromString($input);

            // Test structure
            $headers = iterator_to_array($markdownFile->headers());
            expect($headers)->toHaveCount(7);

            $codeblocks = iterator_to_array($markdownFile->codeBlocks());
            expect($codeblocks)->toHaveCount(4);

            // Test specific blocks
            $phpBlock = $codeblocks[0];
            expect($phpBlock->language)->toBe('php')
                ->and($phpBlock->id)->toBe('auth')
                ->and($phpBlock->metadata)->toBe(['marked' => true, 'secure' => 'high', 'id' => 'auth']);  // includes extracted ID

            $getBlock = $codeblocks[1];
            expect($getBlock->language)->toBe('javascript')
                ->and($getBlock->metadata)->toBe(['example' => 'get']);

            $postBlock = $codeblocks[2];
            expect($postBlock->language)->toBe('javascript')
                ->and($postBlock->id)->toBe('post1')
                ->and($postBlock->metadata)->toBe(['example' => 'post', 'id' => 'post1']);

            $jsonBlock = $codeblocks[3];
            expect($jsonBlock->language)->toBe('json')
                ->and($jsonBlock->metadata)->toBe([]);

            // Test round-trip
            $reconstructed = $markdownFile->toString();
            expect($reconstructed)->toContain('# API Documentation')
                ->and($reconstructed)->toContain('```php')
                ->and($reconstructed)->toContain('```javascript')
                ->and($reconstructed)->toContain('```json');
        });
    });

    describe('Round-trip fidelity', function () {
        it('preserves source markdown with embedded codeblock IDs unchanged', function () {
            $input = <<<'MARKDOWN'
# Documentation

This is sample content.

## Code Example

```php
// @doctest id="auth"
<?php
$token = authenticate();
?>
```

Some text between blocks.

```javascript
// @doctest id="fetch"
fetch('/api/data')
  .then(response => response.json());
```

## Conclusion

That's it!
MARKDOWN;

            $markdownFile = MarkdownFile::fromString($input);
            $reconstructed = $markdownFile->toString();
            
            // The reconstructed output should preserve the structure and content
            // but may add snippet IDs where missing or reformat existing ones
            expect($reconstructed)
                ->toContain('# Documentation')
                ->and($reconstructed)->toContain('## Code Example')
                ->and($reconstructed)->toContain('## Conclusion')
                ->and($reconstructed)->toContain('This is sample content.')
                ->and($reconstructed)->toContain('Some text between blocks.')
                ->and($reconstructed)->toContain("That's it!")
                ->and($reconstructed)->toContain('```php')
                ->and($reconstructed)->toContain('```javascript')
                ->and($reconstructed)->toContain('$token = authenticate();')
                ->and($reconstructed)->toContain("fetch('/api/data')")
                ->and($reconstructed)->toContain('@doctest id="auth"')
                ->and($reconstructed)->toContain('@doctest id="fetch"');
            
            // Parse the reconstructed markdown to ensure it's still valid
            $reparsed = MarkdownFile::fromString($reconstructed);
            $headers = iterator_to_array($reparsed->headers());
            $codeblocks = iterator_to_array($reparsed->codeBlocks());
            
            expect($headers)->toHaveCount(3)
                ->and($codeblocks)->toHaveCount(2)
                ->and(CodeBlockIdentifier::isValid($codeblocks[0]->id))->toBeTrue()
                ->and(CodeBlockIdentifier::isValid($codeblocks[1]->id))->toBeTrue()
                ->and($codeblocks[0]->id)->toBe('auth')
                ->and($codeblocks[1]->id)->toBe('fetch');
        });

        it('handles markdown without embedded IDs by adding them', function () {
            $input = <<<'MARKDOWN'
# Simple Example

```php
echo "Hello World";
```

```python
print("Hello World")
```
MARKDOWN;

            $markdownFile = MarkdownFile::fromString($input);
            $reconstructed = $markdownFile->toString();
            
            // Should contain the original content plus generated snippet IDs
            expect($reconstructed)
                ->toContain('# Simple Example')
                ->toContain('```php')
                ->toContain('```python')
                ->toContain('echo "Hello World"')
                ->toContain('print("Hello World")')
                ->toContain('@doctest id='); // Should have generated IDs
            
            // Verify IDs were generated for code blocks
            $codeblocks = iterator_to_array($markdownFile->codeBlocks());
            expect($codeblocks)->toHaveCount(2);
            expect(CodeBlockIdentifier::isValid($codeblocks[0]->id))->toBeTrue();
            expect(CodeBlockIdentifier::isValid($codeblocks[1]->id))->toBeTrue();
        });

        it('preserves complex formatting and mixed content', function () {
            $input = <<<'MARKDOWN'
# Main Title

Intro paragraph with **bold** and *italic* text.

## Section A

- List item 1
- List item 2

### Subsection

```bash
# @doctest id="setup"
npm install
npm run build
```

Regular paragraph.

```json
{
  "name": "example",
  "version": "1.0.0"
}
```

Final paragraph.
MARKDOWN;

            $markdownFile = MarkdownFile::fromString($input);
            $reconstructed = $markdownFile->toString();
            
            // Verify all content is preserved
            expect($reconstructed)
                ->toContain('# Main Title')
                ->toContain('## Section A') 
                ->toContain('### Subsection')
                ->toContain('Intro paragraph with **bold** and *italic* text.')
                ->toContain('- List item 1')
                ->toContain('- List item 2')
                ->toContain('```bash')
                ->toContain('```json')
                ->toContain('npm install')
                ->toContain('npm run build')
                ->toContain('"name": "example"')
                ->toContain('"version": "1.0.0"')
                ->toContain('Regular paragraph.')
                ->toContain('Final paragraph.')
                ->toContain('@doctest id="setup"');
            
            // Ensure parsing still works correctly
            $reparsed = MarkdownFile::fromString($reconstructed);
            expect(iterator_to_array($reparsed->headers()))->toHaveCount(3);
            expect(iterator_to_array($reparsed->codeBlocks()))->toHaveCount(2);
        });
    });

    describe('Code quotes extraction', function () {
        it('extracts inline code quotes from content', function () {
            $input = <<<'MARKDOWN'
            # Documentation
            
            This text contains `ClassName` and `methodName()` inline code.
            
            Another paragraph with `someVariable` reference.
            
            ```php
            // This is a code block, not inline code
            echo "hello";
            ```
            
            More text with `finalQuote`.
            MARKDOWN;

            $markdownFile = MarkdownFile::fromString($input);
            $codeQuotes = iterator_to_array($markdownFile->codeQuotes());

            expect($codeQuotes)->toHaveCount(4)
                ->and($codeQuotes)->toContain('ClassName')
                ->and($codeQuotes)->toContain('methodName()')
                ->and($codeQuotes)->toContain('someVariable')
                ->and($codeQuotes)->toContain('finalQuote');
        });

        it('handles document without inline code quotes', function () {
            $input = <<<'MARKDOWN'
            # Title
            
            This is plain text without any inline code.
            
            ```php
            echo "This is a code block";
            ```
            MARKDOWN;

            $markdownFile = MarkdownFile::fromString($input);
            $codeQuotes = iterator_to_array($markdownFile->codeQuotes());

            expect($codeQuotes)->toBeEmpty();
        });

        it('extracts code quotes from content only', function () {
            $input = <<<'MARKDOWN'
            # Using ApiClient Class
            
            The `ApiClient` provides methods like `getData()` and `postData()`.
            
            ## Configuration with ConfigManager
            
            Set options using `setOption()` method.
            MARKDOWN;

            $markdownFile = MarkdownFile::fromString($input);
            $codeQuotes = iterator_to_array($markdownFile->codeQuotes());

            expect($codeQuotes)->toHaveCount(4)
                ->and($codeQuotes)->toContain('ApiClient')
                ->and($codeQuotes)->toContain('getData()')
                ->and($codeQuotes)->toContain('postData()')
                ->and($codeQuotes)->toContain('setOption()');
        });

        it('handles empty document', function () {
            $markdownFile = MarkdownFile::fromString('');
            $codeQuotes = iterator_to_array($markdownFile->codeQuotes());

            expect($codeQuotes)->toBeEmpty();
        });

        it('extracts code quotes with special characters', function () {
            $input = <<<'MARKDOWN'
            Use `user->getName()` and `$config["key"]` in your code.
            
            Constants like `MAX_ITEMS` and `DB_CONNECTION_STRING` are important.
            MARKDOWN;

            $markdownFile = MarkdownFile::fromString($input);
            $codeQuotes = iterator_to_array($markdownFile->codeQuotes());

            expect($codeQuotes)->toHaveCount(4)
                ->and($codeQuotes)->toContain('user->getName()')
                ->and($codeQuotes)->toContain('$config["key"]')
                ->and($codeQuotes)->toContain('MAX_ITEMS')
                ->and($codeQuotes)->toContain('DB_CONNECTION_STRING');
        });
    });
});