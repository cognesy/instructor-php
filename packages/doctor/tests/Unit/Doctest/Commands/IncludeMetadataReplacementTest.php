<?php

use Cognesy\Doctor\Doctest\Commands\ExtractCodeBlocks;
use Cognesy\Doctor\Doctest\DocRepo\DocRepository;
use Cognesy\Doctor\Markdown\MarkdownFile;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

describe('Include Metadata Replacement', function () {
    beforeEach(function () {
        // Create in-memory file system mock
        $this->files = [];
        $this->docRepository = $this->createMock(DocRepository::class);
        
        // Configure docRepository mock
        $this->docRepository->method('readFile')
            ->willReturnCallback(fn($path) => $this->files[$path] ?? throw new \InvalidArgumentException("File not found: $path"));
        
        $this->docRepository->method('writeFile')
            ->willReturnCallback(function($path, $content) {
                $this->files[$path] = $content;
            });
            
        $this->docRepository->method('fileExists')
            ->willReturnCallback(fn($path) => isset($this->files[$path]));

        $this->docRepository->method('createBackup')
            ->willReturn('/backup/path.bak');

        // Create command with mocked dependencies
        $this->command = new ExtractCodeBlocks($this->docRepository);
        
        // Set up command tester
        $application = new Application();
        $application->add($this->command);
        $this->commandTester = new CommandTester($this->command);
    });

    describe('include metadata generation', function () {
        it('replaces extracted code blocks with include metadata', function () {
            $originalMarkdown = <<<'MARKDOWN'
---
doctest_case_dir: examples/basic
doctest_case_prefix: test_
doctest_min_lines: 1
doctest_included_types: ["php"]
---

# Test Document

```php id="hello_world"
echo "Hello World";
echo "This is a test";
```

```javascript
console.log("Should not be extracted");
```

```php id="another_example"  
function greet($name) {
    return "Hello, $name!";
}
```
MARKDOWN;

            $this->files['/test/doc.md'] = $originalMarkdown;

            $this->commandTester->execute([
                '--source' => '/test/doc.md',
                '--modify-source' => true
            ]);

            expect($this->commandTester->getStatusCode())->toBe(0);
            
            $modifiedContent = $this->files['/test/doc.md'];
            
            // Check that PHP code blocks were replaced with include metadata
            expect($modifiedContent)->toContain('include="examples/basic/test_hello_world.php"');
            expect($modifiedContent)->toContain('include="examples/basic/test_another_example.php"');
            
            // Check that non-included blocks remain unchanged
            expect($modifiedContent)->toContain('console.log("Should not be extracted")');
            
            // Check that extracted code is replaced with placeholder
            expect($modifiedContent)->toContain('Code extracted - will be included from external file');
            expect($modifiedContent)->not()->toContain('echo "Hello World"');
            expect($modifiedContent)->not()->toContain('function greet($name)');
        });

        it('preserves non-extractable code blocks unchanged', function () {
            $originalMarkdown = <<<'MARKDOWN'
---
doctest_case_dir: examples/test
doctest_case_prefix: test_
doctest_min_lines: 5
doctest_included_types: ["php"]
---

# Test Document

```php id="too_short"
echo "Short";
```

```python id="wrong_language"
print("Wrong language")
print("Multiple lines")
print("But excluded")
```

```php id="long_enough"
echo "Line 1";
echo "Line 2";
echo "Line 3";
echo "Line 4";
echo "Line 5";
```
MARKDOWN;

            $this->files['/test/doc.md'] = $originalMarkdown;

            $this->commandTester->execute([
                '--source' => '/test/doc.md',
                '--modify-source' => true
            ]);

            expect($this->commandTester->getStatusCode())->toBe(0);
            
            $modifiedContent = $this->files['/test/doc.md'];
            
            // Short PHP block should remain unchanged (below min lines)
            expect($modifiedContent)->toContain('echo "Short"');
            expect($modifiedContent)->not()->toContain('include=');
            
            // Python block should remain unchanged (wrong language)
            expect($modifiedContent)->toContain('print("Wrong language")');
            
            // Long PHP block should be replaced with include
            expect($modifiedContent)->toContain('include="examples/test/test_long_enough.php"');
            expect($modifiedContent)->not()->toContain('echo "Line 1"');
        });

        it('generates correct include paths for different case directories', function () {
            $originalMarkdown = <<<'MARKDOWN'
---
doctest_case_dir: deep/nested/path
doctest_case_prefix: prefix_
doctest_min_lines: 1
doctest_included_types: ["php"]
---

```php id="nested_example"
echo "Nested path test";
```
MARKDOWN;

            $this->files['/test/doc.md'] = $originalMarkdown;

            $this->commandTester->execute([
                '--source' => '/test/doc.md',
                '--modify-source' => true
            ]);

            expect($this->commandTester->getStatusCode())->toBe(0);
            
            $modifiedContent = $this->files['/test/doc.md'];
            expect($modifiedContent)->toContain('include="deep/nested/path/prefix_nested_example.php"');
        });
    });

    describe('include metadata format compatibility', function () {
        it('generates include paths compatible with GenerateDocs expectations', function () {
            // This test ensures the include paths work with the existing GenerateDocs command
            $originalMarkdown = <<<'MARKDOWN'
---
doctest_case_dir: codeblocks/D03_Docs_HTTP/BasicHttpMiddleware
doctest_case_prefix: code_
doctest_min_lines: 1
doctest_included_types: ["php"]
---

```php id="middleware_example"
class BasicMiddleware {
    public function handle($request) {
        return $request;
    }
}
```
MARKDOWN;

            $this->files['/docs/middleware.md'] = $originalMarkdown;

            $this->commandTester->execute([
                '--source' => '/docs/middleware.md',
                '--modify-source' => true
            ]);

            expect($this->commandTester->getStatusCode())->toBe(0);
            
            $modifiedContent = $this->files['/docs/middleware.md'];
            
            // The generated include path should match GenerateDocs expectations
            expect($modifiedContent)->toContain('include="codeblocks/D03_Docs_HTTP/BasicHttpMiddleware/code_middleware_example.php"');
            
            // Parse the modified markdown to verify it would work with GenerateDocs
            $markdownFile = MarkdownFile::fromString($modifiedContent, '/docs/middleware.md');
            $codeBlocks = iterator_to_array($markdownFile->codeBlocks());
            
            expect($codeBlocks)->toHaveCount(1);
            expect($codeBlocks[0]->metadata('include'))->toBe('codeblocks/D03_Docs_HTTP/BasicHttpMiddleware/code_middleware_example.php');
        });

        it('preserves existing metadata while adding include', function () {
            $originalMarkdown = <<<'MARKDOWN'
---
doctest_case_dir: examples/test
doctest_case_prefix: test_
doctest_min_lines: 1
doctest_included_types: ["php"]
---

```php id="with_metadata" title="Example Code" description="A test example"
echo "Hello World";
```
MARKDOWN;

            $this->files['/test/doc.md'] = $originalMarkdown;

            $this->commandTester->execute([
                '--source' => '/test/doc.md',
                '--modify-source' => true
            ]);

            expect($this->commandTester->getStatusCode())->toBe(0);
            
            // Parse the modified markdown to check metadata preservation
            $modifiedContent = $this->files['/test/doc.md'];
            $markdownFile = MarkdownFile::fromString($modifiedContent, '/test/doc.md');
            $codeBlocks = iterator_to_array($markdownFile->codeBlocks());
            
            expect($codeBlocks)->toHaveCount(1);
            $codeBlock = $codeBlocks[0];
            
            // Check that existing metadata is preserved
            expect($codeBlock->metadata('title'))->toBe('Example Code');
            expect($codeBlock->metadata('description'))->toBe('A test example');
            
            // Check that include metadata was added
            expect($codeBlock->metadata('include'))->toBe('examples/test/test_with_metadata.php');
        });
    });

    describe('edge cases and error conditions', function () {
        it('handles code blocks without explicit IDs', function () {
            $originalMarkdown = <<<'MARKDOWN'
---
doctest_case_dir: examples/test
doctest_case_prefix: test_
doctest_min_lines: 1
doctest_included_types: ["php"]
---

```php
echo "Auto-generated ID";
```
MARKDOWN;

            $this->files['/test/doc.md'] = $originalMarkdown;

            $this->commandTester->execute([
                '--source' => '/test/doc.md',
                '--modify-source' => true
            ]);

            expect($this->commandTester->getStatusCode())->toBe(0);
            
            $modifiedContent = $this->files['/test/doc.md'];
            
            // Should generate include with auto-generated ID
            expect($modifiedContent)->toContain('include="examples/test/test_');
            expect($modifiedContent)->toContain('.php"');
        });

        it('handles empty doctest configuration gracefully', function () {
            $originalMarkdown = <<<'MARKDOWN'
# Document without doctest config

```php
echo "Should not be processed";
```
MARKDOWN;

            $this->files['/test/doc.md'] = $originalMarkdown;

            $this->commandTester->execute([
                '--source' => '/test/doc.md',
                '--modify-source' => true
            ]);

            expect($this->commandTester->getStatusCode())->toBe(0);
            
            $modifiedContent = $this->files['/test/doc.md'];
            
            // Content should remain unchanged
            expect($modifiedContent)->toContain('echo "Should not be processed"');
            expect($modifiedContent)->not()->toContain('include=');
        });

        it('maintains PHP tag information in replaced blocks', function () {
            $originalMarkdown = <<<'MARKDOWN'
---
doctest_case_dir: examples/test
doctest_case_prefix: test_
doctest_min_lines: 1
doctest_included_types: ["php"]
---

```php id="with_php_tags"
<?php
echo "With PHP tags";
?>
```
MARKDOWN;

            $this->files['/test/doc.md'] = $originalMarkdown;

            $this->commandTester->execute([
                '--source' => '/test/doc.md',
                '--modify-source' => true
            ]);

            expect($this->commandTester->getStatusCode())->toBe(0);
            
            // Parse the modified markdown to check PHP tag preservation
            $modifiedContent = $this->files['/test/doc.md'];
            $markdownFile = MarkdownFile::fromString($modifiedContent, '/test/doc.md');
            $codeBlocks = iterator_to_array($markdownFile->codeBlocks());
            
            expect($codeBlocks)->toHaveCount(1);
            $codeBlock = $codeBlocks[0];
            
            // PHP tag information should be preserved even after replacement
            expect($codeBlock->hasPhpTags())->toBeTrue();
            expect($codeBlock->metadata('include'))->toBe('examples/test/test_with_php_tags.php');
        });
    });
});