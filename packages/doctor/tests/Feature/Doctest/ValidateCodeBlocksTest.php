<?php

use Cognesy\Doctor\Doctest\Commands\ValidateCodeBlocks;
use Cognesy\Utils\Files;
use Symfony\Component\Console\Tester\CommandTester;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/validate_code_blocks_test_' . uniqid();
    mkdir($this->tempDir, 0755, true);
});

afterEach(function () {
    if (is_dir($this->tempDir)) {
        Files::removeDirectory($this->tempDir);
    }
});

function put_md(string $path, string $content): void {
    $dir = dirname($path);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents($path, $content);
}

it('validates single file and shows paths', function () {
    $root = $this->tempDir;
    $mdPath = "$root/docs/page.md";
    $md = <<<'MD'
---
doctest_case_dir: examples
doctest_case_prefix: page_
doctest_min_lines: 1
doctest_included_types: ["php"]
---

```php id="demo"
echo "x";
```
MD;
    put_md($mdPath, $md);

    $cmd = new ValidateCodeBlocks();
    $tester = new CommandTester($cmd);
    $exit = $tester->execute([
        '--source' => $mdPath,
        '--show-paths' => true,
    ], [ 'decorated' => false ]);

    $display = $tester->getDisplay();
    // Expect missing since the extracted file does not exist
    expect($display)->toContain('✖');
    expect($exit)->toBe(1);
});

it('validates directory and reports summary', function () {
    $root = $this->tempDir;
    $dir = "$root/src";
    put_md("$dir/a.md", <<<'MD'
---
doctest_case_dir: examples
doctest_case_prefix: a_
doctest_min_lines: 1
doctest_included_types: ["php"]
---

```php id="one"
echo "a";
```
MD);
    put_md("$dir/b.md", <<<'MD'
---
doctest_case_dir: examples
doctest_case_prefix: b_
doctest_min_lines: 1
doctest_included_types: ["php"]
---

```php id="two"
echo "b";
```
MD);

    $cmd = new ValidateCodeBlocks();
    $tester = new CommandTester($cmd);
    $exit = $tester->execute([
        '--source-dir' => $dir,
        '--extensions' => 'md',
        '--show-progress' => true,
    ], [ 'decorated' => false ]);

    $display = $tester->getDisplay();
    expect($display)->toContain('files with extensions [md]');
    expect($display)->toContain('blocks');
    // Exit code non-zero because files are missing
    expect($exit)->toBe(1);
});

