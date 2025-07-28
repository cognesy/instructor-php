<?php

use Cognesy\Doctor\Doctest\DoctestFile;
use Cognesy\Doctor\Markdown\MarkdownFile;

describe('Doctest Default Metadata', function () {
    describe('default caseDir behavior', function () {
        it('uses default caseDir when not specified', function () {
            $markdown = <<<'MARKDOWN'
---
doctest_min_lines: 1
doctest_included_types: ["php"]
---

# Test Document

```php id="example"
echo "Hello World";
```
MARKDOWN;

            $markdownFile = MarkdownFile::fromString($markdown, '/test/doc.md');
            $doctests = iterator_to_array(DoctestFile::fromMarkdown($markdownFile));

            expect($doctests)->toHaveCount(1);
            expect($doctests[0]->sourceMarkdown->caseDir)->toBe('examples');
        });

        it('uses explicit caseDir when specified', function () {
            $markdown = <<<'MARKDOWN'
---
doctest_case_dir: custom/directory
doctest_min_lines: 1
doctest_included_types: ["php"]
---

# Test Document

```php id="example"
echo "Hello World";
```
MARKDOWN;

            $markdownFile = MarkdownFile::fromString($markdown, '/test/doc.md');
            $doctests = iterator_to_array(DoctestFile::fromMarkdown($markdownFile));

            expect($doctests)->toHaveCount(1);
            expect($doctests[0]->sourceMarkdown->caseDir)->toBe('custom/directory');
        });

        it('uses default when caseDir is empty string', function () {
            $markdown = <<<'MARKDOWN'
---
doctest_case_dir: ""
doctest_min_lines: 1
doctest_included_types: ["php"]
---

# Test Document

```php id="example"
echo "Hello World";
```
MARKDOWN;

            $markdownFile = MarkdownFile::fromString($markdown, '/test/doc.md');
            $doctests = iterator_to_array(DoctestFile::fromMarkdown($markdownFile));

            expect($doctests)->toHaveCount(1);
            expect($doctests[0]->sourceMarkdown->caseDir)->toBe('examples');
        });
    });

    describe('auto-generated casePrefix behavior', function () {
        it('generates casePrefix from simple filename', function () {
            $markdown = <<<'MARKDOWN'
---
doctest_min_lines: 1
doctest_included_types: ["php"]
---

```php id="example"
echo "Hello World";
```
MARKDOWN;

            $markdownFile = MarkdownFile::fromString($markdown, '/docs/introduction.md');
            $doctests = iterator_to_array(DoctestFile::fromMarkdown($markdownFile));

            expect($doctests)->toHaveCount(1);
            expect($doctests[0]->sourceMarkdown->casePrefix)->toBe('introduction_');
        });

        it('generates casePrefix from filename with numbers', function () {
            $markdown = <<<'MARKDOWN'
---
doctest_min_lines: 1
doctest_included_types: ["php"]
---

```php id="example"
echo "Hello World";
```
MARKDOWN;

            $markdownFile = MarkdownFile::fromString($markdown, '/docs/01_getting_started.md');
            $doctests = iterator_to_array(DoctestFile::fromMarkdown($markdownFile));

            expect($doctests)->toHaveCount(1);
            expect($doctests[0]->sourceMarkdown->casePrefix)->toBe('gettingStarted_');
        });

        it('generates casePrefix from complex filename with multiple separators', function () {
            $markdown = <<<'MARKDOWN'
---
doctest_min_lines: 1
doctest_included_types: ["php"]
---

```php id="example"
echo "Hello World";
```
MARKDOWN;

            $markdownFile = MarkdownFile::fromString($markdown, '/docs/03-advanced-usage-patterns.md');
            $doctests = iterator_to_array(DoctestFile::fromMarkdown($markdownFile));

            expect($doctests)->toHaveCount(1);
            expect($doctests[0]->sourceMarkdown->casePrefix)->toBe('advancedUsagePatterns_');
        });

        it('generates casePrefix from filename with underscores and numbers', function () {
            $markdown = <<<'MARKDOWN'
---
doctest_min_lines: 1
doctest_included_types: ["php"]
---

```php id="example"
echo "Hello World";
```
MARKDOWN;

            $markdownFile = MarkdownFile::fromString($markdown, '/docs/05_api_reference_guide.md');
            $doctests = iterator_to_array(DoctestFile::fromMarkdown($markdownFile));

            expect($doctests)->toHaveCount(1);
            expect($doctests[0]->sourceMarkdown->casePrefix)->toBe('apiReferenceGuide_');
        });

        it('uses explicit casePrefix when specified', function () {
            $markdown = <<<'MARKDOWN'
---
doctest_case_prefix: custom_prefix_
doctest_min_lines: 1
doctest_included_types: ["php"]
---

```php id="example"
echo "Hello World";
```
MARKDOWN;

            $markdownFile = MarkdownFile::fromString($markdown, '/docs/01_introduction.md');
            $doctests = iterator_to_array(DoctestFile::fromMarkdown($markdownFile));

            expect($doctests)->toHaveCount(1);
            expect($doctests[0]->sourceMarkdown->casePrefix)->toBe('custom_prefix_');
        });

        it('uses auto-generated when casePrefix is empty string', function () {
            $markdown = <<<'MARKDOWN'
---
doctest_case_prefix: ""
doctest_min_lines: 1
doctest_included_types: ["php"]
---

```php id="example"
echo "Hello World";
```
MARKDOWN;

            $markdownFile = MarkdownFile::fromString($markdown, '/docs/api_guide.md');
            $doctests = iterator_to_array(DoctestFile::fromMarkdown($markdownFile));

            expect($doctests)->toHaveCount(1);
            expect($doctests[0]->sourceMarkdown->casePrefix)->toBe('apiGuide_');
        });
    });

    describe('generated codePath with defaults', function () {
        it('generates correct codePath with default values', function () {
            $markdown = <<<'MARKDOWN'
---
doctest_min_lines: 1
doctest_included_types: ["php"]
---

```php id="hello_world"
echo "Hello World";
```
MARKDOWN;

            $markdownFile = MarkdownFile::fromString($markdown, '/docs/tutorial.md');
            $doctests = iterator_to_array(DoctestFile::fromMarkdown($markdownFile));

            expect($doctests)->toHaveCount(1);
            expect($doctests[0]->codePath)->toBe('examples/tutorial_hello_world.php');
        });

        it('generates correct codePath for JavaScript', function () {
            $markdown = <<<'MARKDOWN'
---
doctest_min_lines: 1
doctest_included_types: ["javascript"]
---

```javascript id="hello_world"
console.log("Hello World");
```
MARKDOWN;

            $markdownFile = MarkdownFile::fromString($markdown, '/docs/02_js_examples.md');
            $doctests = iterator_to_array(DoctestFile::fromMarkdown($markdownFile));

            expect($doctests)->toHaveCount(1);
            expect($doctests[0]->codePath)->toBe('examples/jsExamples_hello_world.js');
        });

        it('handles edge case filenames gracefully', function () {
            $markdown = <<<'MARKDOWN'
---
doctest_min_lines: 1
doctest_included_types: ["php"]
---

```php id="test"
echo "Test";
```
MARKDOWN;

            $markdownFile = MarkdownFile::fromString($markdown, '/docs/123_456_numeric.md');
            $doctests = iterator_to_array(DoctestFile::fromMarkdown($markdownFile));

            expect($doctests)->toHaveCount(1);
            expect($doctests[0]->sourceMarkdown->casePrefix)->toBe('numeric_');
            expect($doctests[0]->codePath)->toBe('examples/numeric_test.php');
        });
    });

    describe('helper methods', function () {
        it('getEffectiveCaseDir returns correct values', function () {
            // With explicit value
            $markdownWithExplicit = MarkdownFile::fromString('---
doctest_case_dir: custom/path
---
# Test', '/test.md');
            expect(DoctestFile::getEffectiveCaseDir($markdownWithExplicit))->toBe('custom/path');

            // Without explicit value
            $markdownWithoutExplicit = MarkdownFile::fromString('---
title: Test
---
# Test', '/test.md');
            expect(DoctestFile::getEffectiveCaseDir($markdownWithoutExplicit))->toBe('examples');

            // With empty value
            $markdownWithEmpty = MarkdownFile::fromString('---
doctest_case_dir: ""
---
# Test', '/test.md');
            expect(DoctestFile::getEffectiveCaseDir($markdownWithEmpty))->toBe('examples');
        });

        it('getEffectiveCasePrefix returns correct values', function () {
            // With explicit value
            $markdownWithExplicit = MarkdownFile::fromString('---
doctest_case_prefix: explicit_
---
# Test', '/docs/filename.md');
            expect(DoctestFile::getEffectiveCasePrefix($markdownWithExplicit))->toBe('explicit_');

            // Without explicit value
            $markdownWithoutExplicit = MarkdownFile::fromString('---
title: Test
---
# Test', '/docs/my_file.md');
            expect(DoctestFile::getEffectiveCasePrefix($markdownWithoutExplicit))->toBe('myFile_');

            // With empty value
            $markdownWithEmpty = MarkdownFile::fromString('---
doctest_case_prefix: ""
---
# Test', '/docs/test_document.md');
            expect(DoctestFile::getEffectiveCasePrefix($markdownWithEmpty))->toBe('testDocument_');
        });
    });
});