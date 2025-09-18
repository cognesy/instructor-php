<?php

use Cognesy\Doctor\Doctest\Commands\ExtractCodeBlocks;
use Cognesy\Doctor\Doctest\Services\DocRepository;
use Cognesy\Utils\Files;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/extract_code_blocks_test_' . uniqid();
    mkdir($this->tempDir, 0755, true);
});

afterEach(function () {
    if (is_dir($this->tempDir)) {
        Files::removeDirectory($this->tempDir);
    }
});

function put_file(string $path, string $content): void {
    $dir = dirname($path);
    if (!is_dir($dir)) { mkdir($dir, 0755, true); }
    file_put_contents($path, $content);
}

it('extracts a single file with regions and prints metrics', function () {
    $root = $this->tempDir;
    $mdPath = "$root/docs/page.md";
    $md = <<<'MD'
---
doctest_case_dir: examples
doctest_min_lines: 1
doctest_included_types: ["php"]
---

```php
// @doctest id="demo"
echo "main";
// @doctest-region-start name=part
echo "region";
// @doctest-region-end
```
MD;
    put_file($mdPath, $md);

    $cmd = new ExtractCodeBlocks(new DocRepository(new Filesystem()));
    $tester = new CommandTester($cmd);
    $tester->execute([
        '--source' => $mdPath,
    ], [ 'decorated' => false ]);

    // Expect files written relative to markdown
    expect(file_exists("$root/docs/examples/page_demo.php"))->toBeTrue();
    expect(file_exists("$root/docs/examples/page_demo_part.php"))->toBeTrue();

    // Metrics summary printed
    $display = $tester->getDisplay();
    expect($display)->toContain('• EXTRACT 1 files');
});

it('respects target-dir overlay in single-file mode', function () {
    $root = $this->tempDir;
    $mdPath = "$root/docs/page.md";
    put_file($mdPath, <<<'MD'
---
doctest_case_dir: examples
doctest_min_lines: 1
doctest_included_types: ["php"]
---

```php id="demo"
echo "x";
```
MD);

    $target = "$root/out";
    $cmd = new ExtractCodeBlocks(new DocRepository(new Filesystem()));
    $tester = new CommandTester($cmd);
    $tester->execute([
        '--source' => $mdPath,
        '--target-dir' => $target,
    ], [ 'decorated' => false ]);

    expect(file_exists("$target/examples/page_demo.php"))->toBeTrue();
});

it('dry-run prints metrics and writes nothing', function () {
    $root = $this->tempDir;
    $mdPath = "$root/docs/page.md";
    put_file($mdPath, <<<'MD'
---
doctest_case_dir: examples
doctest_min_lines: 1
doctest_included_types: ["php"]
---

```php id="demo"
echo "x";
```
MD);

    $cmd = new ExtractCodeBlocks(new DocRepository(new Filesystem()));
    $tester = new CommandTester($cmd);
    $tester->execute([
        '--source' => $mdPath,
        '--dry-run' => true,
    ], [ 'decorated' => false ]);

    $display = $tester->getDisplay();
    expect($display)->toContain('Dry run completed. No files were written.');
    expect($display)->toContain('• EXTRACT 1 files');
    expect(file_exists("$root/docs/examples/page_demo.php"))->toBeFalse();
});

it('directory mode extracts only main snippets and prints metrics', function () {
    $root = $this->tempDir;
    $dir = "$root/src";
    put_file("$dir/a.md", <<<'MD'
---
doctest_case_dir: examples
doctest_min_lines: 1
doctest_included_types: ["php"]
---

```php
// @doctest id="one"
echo "a";
// @doctest-region-start name=r
echo "r";
// @doctest-region-end
```
MD);
    put_file("$dir/b.md", <<<'MD'
---
doctest_case_dir: examples
doctest_min_lines: 1
doctest_included_types: ["php"]
---

```php id="two"
echo "b";
```
MD);

    $cmd = new ExtractCodeBlocks(new DocRepository(new Filesystem()));
    $tester = new CommandTester($cmd);
    $tester->execute([
        '--source-dir' => $dir,
        '--extensions' => 'md',
    ], [ 'decorated' => false ]);

    // Only main snippets are written in directory mode (no regions)
    expect(file_exists("$dir/examples/a_one.php"))->toBeTrue();
    expect(file_exists("$dir/examples/a_one_r.php"))->toBeFalse();
    expect(file_exists("$dir/examples/b_two.php"))->toBeTrue();

    $display = $tester->getDisplay();
    expect($display)->toContain('Successfully extracted 2 code blocks from 2 files.');
    expect($display)->toContain('• EXTRACT 2 files');
});
