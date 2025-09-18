<?php

use Cognesy\Doctor\Doctest\Commands\MarkSnippetsRecursively;
use Cognesy\Doctor\Doctest\Services\DocRepository;
use Cognesy\Utils\Files;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Output\OutputInterface;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/mark_snippets_recursively_test_' . uniqid();
    mkdir($this->tempDir, 0755, true);
});

afterEach(function () {
    if (is_dir($this->tempDir)) {
        Files::removeDirectory($this->tempDir);
    }
});

function write_file(string $path, string $content): void {
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($path, $content);
}

it('filters discovery by extensions and performs dry-run without writes', function () {
    $src = $this->tempDir . '/src';
    $dst = $this->tempDir . '/dst';
    write_file("$src/a.md", "# A\n\nContent");
    write_file("$src/b.mdx", "# B\n\nContent");
    write_file("$src/c.txt", "not considered");

    $cmd = new MarkSnippetsRecursively(new DocRepository(new Filesystem()));
    $tester = new CommandTester($cmd);
    $tester->execute([
        '--source-dir' => $src,
        '--target-dir' => $dst,
        '--extensions' => 'md,mdx',
        '--dry-run' => true,
    ], [ 'decorated' => false ]);

    $display = $tester->getDisplay();
    expect($display)->toContain('Found 2 files to process:');
    expect($display)->toContain('.md: 1 files');
    expect($display)->toContain('.mdx: 1 files');
    expect(file_exists("$dst/a.md"))->toBeFalse();
    expect(file_exists("$dst/b.mdx"))->toBeFalse();
});

it('writes to target preserving directory structure', function () {
    $src = $this->tempDir . '/src';
    $dst = $this->tempDir . '/dst';
    write_file("$src/docs/a.md", "# A\n\nContent");
    write_file("$src/guide/b.mdx", "# B\n\nContent");

    $cmd = new MarkSnippetsRecursively(new DocRepository(new Filesystem()));
    $tester = new CommandTester($cmd);
    $tester->execute([
        '--source-dir' => $src,
        '--target-dir' => $dst,
        '--extensions' => 'md,mdx',
    ], [ 'decorated' => false ]);

    expect(file_exists("$dst/docs/a.md"))->toBeTrue();
    expect(file_exists("$dst/guide/b.mdx"))->toBeTrue();
});

it('reports total snippet count', function () {
    $src = $this->tempDir . '/src';
    $dst = $this->tempDir . '/dst';
    $md = <<<'MD'
# Title

```php
echo "one";
```

```php
echo "two";
```
MD;
    write_file("$src/a.md", $md);

    $cmd = new MarkSnippetsRecursively(new DocRepository(new Filesystem()));
    $tester = new CommandTester($cmd);
    $tester->execute([
        '--source-dir' => $src,
        '--target-dir' => $dst,
        '--extensions' => 'md',
    ], [ 'decorated' => false, 'verbosity' => OutputInterface::VERBOSITY_VERBOSE ]);

    $display = $tester->getDisplay();
    expect($display)->toContain('Total code snippets processed: 2');
});

