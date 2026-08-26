<?php

declare(strict_types=1);

use Cognesy\InstructorHub\Services\ExampleScript;

test('materializes a fenced PHP example beside its Markdown source', function (): void {
    $directory = sys_get_temp_dir().'/instructor-hub-example-'.bin2hex(random_bytes(4));
    mkdir($directory);
    $source = $directory.'/run.php';
    $script = null;
    file_put_contents($source, <<<'MARKDOWN'
---
title: Example
---

```php
<?php echo __DIR__;
```
MARKDOWN);

    try {
        $script = ExampleScript::fromRunPath($source);

        expect($script->path)
            ->not->toBe($source)
            ->toStartWith(realpath($directory).'/');
        expect(trim((string) shell_exec('php '.escapeshellarg($script->path))))->toBe(realpath($directory));

        $temporaryPath = $script->path;
        $script->cleanup();
        expect($temporaryPath)->not->toBeFile();
    } finally {
        $script?->cleanup();
        @unlink($source);
        @rmdir($directory);
    }
});

test('uses a plain PHP example without materializing a temporary script', function (): void {
    $source = tempnam(sys_get_temp_dir(), 'instructor-hub-example-');
    file_put_contents($source, '<?php echo "plain";');

    try {
        $script = ExampleScript::fromRunPath($source);

        expect($script->path)->toBe($source);
        expect(trim((string) shell_exec('php '.escapeshellarg($script->path))))->toBe('plain');
    } finally {
        @unlink($source);
    }
});
