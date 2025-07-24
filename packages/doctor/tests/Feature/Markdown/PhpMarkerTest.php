<?php

use Cognesy\Doctor\Markdown\MarkdownFile;

describe('PHP Marker Integration', function () {
    it('can identify and separate PHP markers from code content', function () {
        $markdown = <<<'MD'
```php
<?php
echo "Hello World";
?>
```
MD;
        
        $markdownFile = MarkdownFile::fromString($markdown);
        
        foreach ($markdownFile->codeBlocks() as $codeblock) {
            expect($codeblock->language)->toBe('php');
            expect($codeblock->content)->toBe("\necho \"Hello World\";\n");  // Content should be clean
            expect($codeblock->hasPhpOpenTag)->toBeTrue();
            expect($codeblock->hasPhpCloseTag)->toBeTrue();
            expect($codeblock->hasPhpTags())->toBeTrue();
            expect($codeblock->getContentWithoutPhpTags())->toBe("\necho \"Hello World\";\n");
        }
    });

    it('handles simple cases with PHP tags only at start and end', function () {
        $markdown = <<<'MD'
```php
<?php
$message = "Hello World";
echo $message;
?>
```
MD;
        
        $markdownFile = MarkdownFile::fromString($markdown);
        
        foreach ($markdownFile->codeBlocks() as $codeblock) {
            expect($codeblock->language)->toBe('php');
            expect($codeblock->hasPhpOpenTag)->toBeTrue();
            expect($codeblock->hasPhpCloseTag)->toBeTrue();
            expect($codeblock->getContentWithoutPhpTags())->toContain('$message = "Hello World"');
            expect($codeblock->getContentWithoutPhpTags())->toContain('echo $message');
            expect($codeblock->getContentWithoutPhpTags())->not->toContain('<?php');
            expect($codeblock->getContentWithoutPhpTags())->not->toContain('?>');
        }
    });

    it('preserves original behavior for non-PHP code blocks', function () {
        $markdown = <<<'MD'
```javascript
console.log("Hello World");
```
MD;
        
        $markdownFile = MarkdownFile::fromString($markdown);
        
        foreach ($markdownFile->codeBlocks() as $codeblock) {
            expect($codeblock->language)->toBe('javascript');
            expect($codeblock->hasPhpOpenTag)->toBeFalse();
            expect($codeblock->hasPhpCloseTag)->toBeFalse();
            expect($codeblock->hasPhpTags())->toBeFalse();
            expect($codeblock->getContentWithoutPhpTags())->toBe('console.log("Hello World");');
        }
    });

    it('can handle PHP opening tag without closing tag', function () {
        $markdown = <<<'MD'
```php
<?php
$name = "World";
echo "Hello $name";
```
MD;
        
        $markdownFile = MarkdownFile::fromString($markdown);
        
        foreach ($markdownFile->codeBlocks() as $codeblock) {
            expect($codeblock->hasPhpOpenTag)->toBeTrue();
            expect($codeblock->hasPhpCloseTag)->toBeFalse();
            expect($codeblock->hasPhpTags())->toBeTrue();
            expect($codeblock->getContentWithoutPhpTags())->toContain('$name = "World"');
            expect($codeblock->getContentWithoutPhpTags())->not->toContain('<?php');
        }
    });

    it('can handle PHP closing tag without opening tag', function () {
        $markdown = <<<'MD'
```php
$name = "World";
echo "Hello $name";
?>
```
MD;
        
        $markdownFile = MarkdownFile::fromString($markdown);
        
        foreach ($markdownFile->codeBlocks() as $codeblock) {
            expect($codeblock->hasPhpOpenTag)->toBeFalse();
            expect($codeblock->hasPhpCloseTag)->toBeTrue();
            expect($codeblock->hasPhpTags())->toBeTrue();
            expect($codeblock->getContentWithoutPhpTags())->toContain('$name = "World"');
            expect($codeblock->getContentWithoutPhpTags())->not->toContain('?>');
        }
    });

    it('maintains proper serialization back to string', function () {
        $original = <<<'MD'
```php
<?php
echo "Hello World";
?>
```
MD;
        
        $markdownFile = MarkdownFile::fromString($original);
        $serialized = $markdownFile->toString();
        
        // The serialization includes a snippet ID comment, so we check that the PHP structure is preserved
        expect($serialized)->toContain('```php')
            ->and($serialized)->toContain('<?php')
            ->and($serialized)->toContain('echo "Hello World";')
            ->and($serialized)->toContain('?>')
            ->and($serialized)->toContain('```')
            ->and($serialized)->toContain('// @doctest id=');
    });
});