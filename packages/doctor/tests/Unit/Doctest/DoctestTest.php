<?php

use Cognesy\Doctor\Doctest\DoctestFile;
use Cognesy\Doctor\Markdown\MarkdownFile;

describe('Doctest', function () {
    describe('ID access and auto-generation', function () {
        it('correctly reads auto-generated IDs from code blocks', function () {
            $markdown = <<<'MARKDOWN'
---
doctest_case_dir: examples/test
doctest_case_prefix: test_
doctest_min_lines: 1
doctest_included_types: ["php"]
---

# Test Document

```php
echo "Hello World";
```
MARKDOWN;

            $markdownFile = MarkdownFile::fromString($markdown, '/test/doc.md');
            $doctests = iterator_to_array(DoctestFile::fromMarkdown($markdownFile));

            expect($doctests)->toHaveCount(1);
            expect($doctests[0]->id)->toHaveLength(4); // 4 hex chars
            expect($doctests[0]->id)->toMatch('/^[a-f0-9]{4}$/'); // hex format
        });

        it('correctly reads explicit IDs from fence parameters', function () {
            $markdown = <<<'MARKDOWN'
---
doctest_case_dir: examples/test
doctest_case_prefix: test_
doctest_min_lines: 1
doctest_included_types: ["php"]
---

# Test Document

```php id="explicit_id"
echo "Hello World";
```
MARKDOWN;

            $markdownFile = MarkdownFile::fromString($markdown, '/test/doc.md');
            $doctests = iterator_to_array(DoctestFile::fromMarkdown($markdownFile));

            expect($doctests)->toHaveCount(1);
            expect($doctests[0]->id)->toBe('explicit_id');
        });

        it('correctly reads explicit IDs from @doctest annotations', function () {
            $markdown = <<<'MARKDOWN'
---
doctest_case_dir: examples/test
doctest_case_prefix: test_
doctest_min_lines: 1
doctest_included_types: ["php"]
---

# Test Document

```php
// @doctest id="annotation_id"
echo "Hello World";
```
MARKDOWN;

            $markdownFile = MarkdownFile::fromString($markdown, '/test/doc.md');
            $doctests = iterator_to_array(DoctestFile::fromMarkdown($markdownFile));

            expect($doctests)->toHaveCount(1);
            expect($doctests[0]->id)->toBe('annotation_id');
        });

        it('excludes code blocks without IDs when metadata access was broken', function () {
            // This test ensures we're not accessing metadata['id'] which would be null/empty
            $markdown = <<<'MARKDOWN'
---
doctest_case_dir: examples/test
doctest_case_prefix: test_
doctest_min_lines: 1
doctest_included_types: ["php"]
---

# Test Document

```php
echo "Hello World";
```
MARKDOWN;

            $markdownFile = MarkdownFile::fromString($markdown, '/test/doc.md');
            
            // Before the fix, this would return 0 doctests because metadata['id'] was null
            // After the fix, it should return 1 doctest with auto-generated ID
            $doctests = iterator_to_array(DoctestFile::fromMarkdown($markdownFile));

            expect($doctests)->toHaveCount(1);
            expect($doctests[0]->id)->not()->toBeEmpty();
            expect($doctests[0]->id)->toHaveLength(4); // Auto-generated 4-char hex ID
        });
    });

    describe('filtering and inclusion', function () {
        it('excludes code blocks not in included types', function () {
            $markdown = <<<'MARKDOWN'
---
doctest_case_dir: examples/test
doctest_case_prefix: test_
doctest_min_lines: 1
doctest_included_types: ["php"]
---

# Test Document

```javascript
console.log("Hello World");
```
MARKDOWN;

            $markdownFile = MarkdownFile::fromString($markdown, '/test/doc.md');
            $doctests = iterator_to_array(DoctestFile::fromMarkdown($markdownFile));

            expect($doctests)->toHaveCount(0);
        });

        it('excludes code blocks below minimum lines', function () {
            $markdown = <<<'MARKDOWN'
---
doctest_case_dir: examples/test
doctest_case_prefix: test_
doctest_min_lines: 5
doctest_included_types: ["php"]
---

# Test Document

```php
echo "Hello";
```
MARKDOWN;

            $markdownFile = MarkdownFile::fromString($markdown, '/test/doc.md');
            $doctests = iterator_to_array(DoctestFile::fromMarkdown($markdownFile));

            expect($doctests)->toHaveCount(0);
        });

        it('includes code blocks that meet all criteria', function () {
            $markdown = <<<'MARKDOWN'
---
doctest_case_dir: examples/test
doctest_case_prefix: test_
doctest_min_lines: 2
doctest_included_types: ["php", "javascript"]
---

# Test Document

```php
echo "Hello";
echo "World";
```

```javascript
console.log("Hello");
console.log("World");
```

```python
print("Should be excluded")
print("Because not in included types")
```
MARKDOWN;

            $markdownFile = MarkdownFile::fromString($markdown, '/test/doc.md');
            $doctests = iterator_to_array(DoctestFile::fromMarkdown($markdownFile));

            expect($doctests)->toHaveCount(2);
            expect($doctests[0]->language)->toBe('php');
            expect($doctests[1]->language)->toBe('javascript');
        });
    });

    describe('doctest properties', function () {
        it('correctly sets all doctest properties', function () {
            $markdown = <<<'MARKDOWN'
---
title: "Test Document"
description: "A test document for doctests"
doctest_case_dir: examples/test
doctest_case_prefix: test_
doctest_min_lines: 1
doctest_included_types: ["php"]
---

# Test Document

```php id="test_example"
echo "Hello World";
echo "Second line";
```
MARKDOWN;

            $markdownFile = MarkdownFile::fromString($markdown, '/test/doc.md');
            $doctests = iterator_to_array(DoctestFile::fromMarkdown($markdownFile));

            expect($doctests)->toHaveCount(1);
            
            $doctest = $doctests[0];
            expect($doctest->id)->toBe('test_example');
            expect($doctest->language)->toBe('php');
            expect($doctest->linesOfCode)->toBe(2);
            expect($doctest->sourceMarkdown->path)->toBe('/test/doc.md');
            expect($doctest->code)->toContain('echo "Hello World"');
            expect($doctest->sourceMarkdown->title)->toBe('Test Document');
            expect($doctest->sourceMarkdown->description)->toBe('A test document for doctests');
            expect($doctest->sourceMarkdown->caseDir)->toBe('examples/test');
            expect($doctest->sourceMarkdown->casePrefix)->toBe('test_');
            expect($doctest->sourceMarkdown->minLines)->toBe(1);
            expect($doctest->sourceMarkdown->includedTypes)->toBe(['php']);
        });
    });
});