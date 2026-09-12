<?php

declare(strict_types=1);

use Cognesy\InstructorHub\Data\Example;
use Cognesy\InstructorHub\Services\EnhancedRunner;

test('executes the original PHP template with documentation and shared state', function (): void {
    $directory = sys_get_temp_dir().'/instructor-hub-template-'.bin2hex(random_bytes(4));
    mkdir($directory);
    $source = $directory.'/run.php';
    file_put_contents($source, <<<'PHP'
---
title: Native PHP Template
---
Documentation before code.
<?php
$message = 'shared state';
echo 'directory:'.basename(__DIR__)."\n";
?>
Documentation between code regions.
<?php
echo "message:{$message}\n";
?>
Documentation after code.
PHP);

    try {
        $result = (new EnhancedRunner(timeoutSeconds: 5))->execute(new Example(
            name: 'NativePhpTemplate',
            runPath: $source,
        ));

        expect($result->isSuccessful())->toBeTrue()
            ->and($result->output)->toContain(
                'title: Native PHP Template',
                'Documentation before code.',
                'directory:'.basename($directory),
                'Documentation between code regions.',
                'message:shared state',
                'Documentation after code.',
            );
    } finally {
        @unlink($source);
        @rmdir($directory);
    }
});
