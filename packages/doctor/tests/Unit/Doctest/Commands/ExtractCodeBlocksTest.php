<?php

use Cognesy\Doctor\Doctest\Commands\ExtractCodeBlocks;
use Cognesy\Doctor\Doctest\DocRepo\DocRepository;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

describe('ExtractCodeBlocks Command', function () {
    beforeEach(function () {
        // Create in-memory file system mock
        $this->files = [];
        $this->mockFilesystem = $this->createMock(Filesystem::class);
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

        // Create command with mocked dependencies
        $this->command = new ExtractCodeBlocks($this->docRepository);
        
        // Set up command tester
        $application = new Application();
        $application->add($this->command);
        $this->commandTester = new CommandTester($this->command);
    });

    describe('single file processing', function () {
        it('extracts code blocks from a single markdown file', function () {
            $markdownContent = <<<'MARKDOWN'
---
doctest_case_dir: examples/test
doctest_case_prefix: test_
doctest_min_lines: 1
doctest_included_types: ["php"]
---

# Test Document

```php id="example1"
echo "Hello World";
echo "Second line";
```

```javascript
console.log("Should be ignored");
```

```php id="example2"
function test() {
    return "test";
}
```
MARKDOWN;

            $this->files['/test/doc.md'] = $markdownContent;

            $this->commandTester->execute([
                '--source' => '/test/doc.md',
                '--dry-run' => true
            ]);

            expect($this->commandTester->getStatusCode())->toBe(0);
            $output = $this->commandTester->getDisplay();
            expect($output)->toContain('Found 2 extractable code blocks');
            expect($output)->toContain('example1');
            expect($output)->toContain('example2');
        });

        it('creates extracted files when not in dry run mode', function () {
            $markdownContent = <<<'MARKDOWN'
---
doctest_case_dir: examples/test
doctest_case_prefix: test_
doctest_min_lines: 1
doctest_included_types: ["php"]
---

# Test Document

```php id="example1"
echo "Hello World";
```
MARKDOWN;

            $this->files['/test/doc.md'] = $markdownContent;

            $this->commandTester->execute([
                '--source' => '/test/doc.md'
            ]);

            expect($this->commandTester->getStatusCode())->toBe(0);
            
            // Check that extracted file was created
            $expectedPath = 'examples/test/test_example1.php';
            expect($this->files)->toHaveKey($expectedPath);
            expect($this->files[$expectedPath])->toContain('echo "Hello World";');
        });

        it('modifies source file when --modify-source is used', function () {
            $markdownContent = <<<'MARKDOWN'
---
doctest_case_dir: examples/test
doctest_case_prefix: test_
doctest_min_lines: 1
doctest_included_types: ["php"]
---

# Test Document

```php id="example1"
echo "Hello World";
```
MARKDOWN;

            $this->files['/test/doc.md'] = $markdownContent;

            $this->commandTester->execute([
                '--source' => '/test/doc.md',
                '--modify-source' => true
            ]);

            expect($this->commandTester->getStatusCode())->toBe(0);
            
            // Check that source file was modified with include metadata
            $modifiedContent = $this->files['/test/doc.md'];
            expect($modifiedContent)->toContain('include="examples/test/test_example1.php"');
            expect($modifiedContent)->toContain('Code extracted - will be included from external file');
        });

        it('creates backup when modifying source file', function () {
            $markdownContent = <<<'MARKDOWN'
---
doctest_case_dir: examples/test  
doctest_case_prefix: test_
doctest_min_lines: 1
doctest_included_types: ["php"]
---

```php
echo "Test";
```
MARKDOWN;

            $this->files['/test/doc.md'] = $markdownContent;
            
            // Mock backup creation
            $this->docRepository->expects($this->once())
                ->method('createBackup')
                ->with('/test/doc.md')
                ->willReturn('/test/doc.md.20231201-120000.bak');

            $this->commandTester->execute([
                '--source' => '/test/doc.md',
                '--modify-source' => true
            ]);

            expect($this->commandTester->getStatusCode())->toBe(0);
        });
    });

    describe('directory processing', function () {
        it('processes multiple markdown files in a directory', function () {
            $markdownContent1 = <<<'MARKDOWN'
---
doctest_case_dir: examples/test1
doctest_case_prefix: test1_
doctest_min_lines: 1
doctest_included_types: ["php"]
---

```php
echo "File 1";
```
MARKDOWN;

            $markdownContent2 = <<<'MARKDOWN'
---
doctest_case_dir: examples/test2
doctest_case_prefix: test2_
doctest_min_lines: 1
doctest_included_types: ["php"]
---

```php
echo "File 2";
```
MARKDOWN;

            $this->files['/test/dir/doc1.md'] = $markdownContent1;
            $this->files['/test/dir/doc2.md'] = $markdownContent2;
            
            // Mock directory traversal
            $this->docRepository->method('fileExists')
                ->willReturnCallback(fn($path) => in_array($path, ['/test/dir/doc1.md', '/test/dir/doc2.md']));

            $this->commandTester->execute([
                '--source-dir' => '/test/dir',
                '--dry-run' => true
            ]);

            expect($this->commandTester->getStatusCode())->toBe(0);
            $output = $this->commandTester->getDisplay();
            expect($output)->toContain('Found 2 files to process');
        });

        it('filters files by extension', function () {
            $this->files['/test/dir/doc.md'] = '# Markdown file';
            $this->files['/test/dir/doc.mdx'] = '# MDX file';
            $this->files['/test/dir/doc.txt'] = 'Text file';

            $this->commandTester->execute([
                '--source-dir' => '/test/dir',
                '--extensions' => 'md',
                '--dry-run' => true
            ]);

            expect($this->commandTester->getStatusCode())->toBe(0);
            $output = $this->commandTester->getDisplay();
            expect($output)->toContain('Found 1 files to process');
        });
    });

    describe('target directory override', function () {
        it('uses custom target directory when specified', function () {
            $markdownContent = <<<'MARKDOWN'
---
doctest_case_dir: examples/original
doctest_case_prefix: test_
doctest_min_lines: 1
doctest_included_types: ["php"]
---

```php id="example"
echo "Hello";
```
MARKDOWN;

            $this->files['/test/doc.md'] = $markdownContent;

            $this->commandTester->execute([
                '--source' => '/test/doc.md',
                '--target-dir' => '/custom/output'
            ]);

            expect($this->commandTester->getStatusCode())->toBe(0);
            
            // Should use custom target directory
            expect($this->files)->toHaveKey('/custom/output/test_example.php');
        });
    });

    describe('error handling', function () {
        it('handles missing source file gracefully', function () {
            $this->commandTester->execute([
                '--source' => '/nonexistent/file.md'
            ]);

            expect($this->commandTester->getStatusCode())->toBe(1);
            $output = $this->commandTester->getDisplay();
            expect($output)->toContain('File not found');
        });

        it('validates extension parameter', function () {
            $this->commandTester->execute([
                '--source-dir' => '/test',
                '--extensions' => ''
            ]);

            expect($this->commandTester->getStatusCode())->toBe(1);
            $output = $this->commandTester->getDisplay();
            expect($output)->toContain('At least one file extension must be specified');
        });

        it('requires either source or source-dir parameter', function () {
            $this->commandTester->execute([]);

            expect($this->commandTester->getStatusCode())->toBe(1);
            $output = $this->commandTester->getDisplay();
            expect($output)->toContain('Either --source or --source-dir must be specified');
        });
    });

    describe('verbose output', function () {
        it('shows detailed information in verbose mode', function () {
            $markdownContent = <<<'MARKDOWN'
---
doctest_case_dir: examples/test
doctest_case_prefix: test_
doctest_min_lines: 1
doctest_included_types: ["php"]
---

```php id="example"
echo "Hello";
```
MARKDOWN;

            $this->files['/test/doc.md'] = $markdownContent;

            $this->commandTester->execute([
                '--source' => '/test/doc.md',
                '--dry-run' => true,
                '-v' => true
            ]);

            expect($this->commandTester->getStatusCode())->toBe(0);
            $output = $this->commandTester->getDisplay();
            expect($output)->toContain('example (php, 1 lines)');
        });
    });
});