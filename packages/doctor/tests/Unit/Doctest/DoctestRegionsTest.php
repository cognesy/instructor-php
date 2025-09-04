<?php

use Cognesy\Doctor\Doctest\DoctestFile;
use Cognesy\Doctor\Markdown\MarkdownFile;

it('detects regions and extracts region content', function () {
    $markdown = <<<'MD'
---
doctest_min_lines: 1
doctest_included_types: ["php"]
---

```php
// @doctest id="demo"
// @doctest-region-start name=part
echo "hello";
// @doctest-region-end
```
MD;

    $md = MarkdownFile::fromString($markdown, '/docs/page.md');
    $doctests = iterator_to_array(DoctestFile::fromMarkdown($md));
    expect($doctests)->toHaveCount(1);
    $d = $doctests[0];

    expect($d->hasRegions())->toBeTrue();
    $regions = $d->getAvailableRegions();
    expect($regions)->toHaveCount(1);
    expect($regions)->toContain('part');
    expect($d->extractRegion('part'))->toContain('echo "hello";');
});

it('extracts id from code via getIdFromCode', function () {
    $markdown = <<<'MD'
---
doctest_min_lines: 1
doctest_included_types: ["php"]
---

```php
// @doctest id="abc"
echo 1;
```
MD;

    $md = MarkdownFile::fromString($markdown, '/docs/page.md');
    $doctests = iterator_to_array(DoctestFile::fromMarkdown($md));
    expect($doctests)->toHaveCount(1);
    expect($doctests[0]->getIdFromCode())->toBe('abc');
});
