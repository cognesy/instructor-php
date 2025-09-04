<?php

use Cognesy\Doctor\Doctest\Services\ValidationService;
use Cognesy\Utils\Files;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/validation_service_test_' . uniqid();
    mkdir($this->tempDir, 0755, true);
});

afterEach(function () {
    if (is_dir($this->tempDir)) {
        Files::removeDirectory($this->tempDir);
    }
});

function put(string $path, string $content): void {
    $dir = dirname($path);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($path, $content);
}

it('resolves include metadata path', function () {
    $md = <<<'MD'
---
title: T
---

```php include="examples/t_demo.php"
// @doctest id="demo"
echo 1;
```
MD;
    $file = $this->tempDir . '/docs/t.md';
    put($file, $md);
    $svc = new ValidationService();
    $result = $svc->validateFile($file);
    expect($result->totalBlocks)->toBe(1);
    expect($result->missingCount())->toBe(1);
    $expected = $result->missingBlocks[0]->expectedPath;
    expect($expected)->toEndWith(DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'examples' . DIRECTORY_SEPARATOR . 't_demo.php');
});

it('resolves legacy inline doctest path', function () {
    $md = <<<'MD'
---
title: T
---

```php
// @doctest id="examples/legacy.php"
echo 1;
```
MD;
    $file = $this->tempDir . '/docs/t.md';
    put($file, $md);
    $svc = new ValidationService();
    $result = $svc->validateFile($file);
    expect($result->totalBlocks)->toBe(1);
    expect($result->missingCount())->toBe(1);
    $expected = $result->missingBlocks[0]->expectedPath;
    expect($expected)->toEndWith(DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'examples' . DIRECTORY_SEPARATOR . 'legacy.php');
});

it('falls back to frontmatter-derived path when no include/legacy path', function () {
    $md = <<<'MD'
---
doctest_case_dir: examples
doctest_case_prefix: intro_
doctest_min_lines: 1
doctest_included_types: ["php"]
---

```php id="demo"
echo 1;
```
MD;
    $file = $this->tempDir . '/docs/intro.md';
    put($file, $md);
    $svc = new ValidationService();
    $result = $svc->validateFile($file);
    expect($result->totalBlocks)->toBe(1);
    expect($result->missingCount())->toBe(1);
    $expected = $result->missingBlocks[0]->expectedPath;
    expect($expected)->toEndWith(DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'examples' . DIRECTORY_SEPARATOR . 'intro_demo.php');
});
