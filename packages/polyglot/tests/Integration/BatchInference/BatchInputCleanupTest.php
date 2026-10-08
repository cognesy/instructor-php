<?php

declare(strict_types=1);

it('removes owned input files after validation failure and abandoned preparation', function () {
    $directory = sys_get_temp_dir().'/polyglot-batch-cleanup-'.bin2hex(random_bytes(8));
    expect(mkdir($directory, 0700))->toBeTrue();

    try {
        $worker = dirname(__DIR__, 2).'/Support/BatchInference/input-cleanup-worker.php';
        $process = proc_open([PHP_BINARY, $worker], [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, null, ['TMPDIR' => $directory]);
        expect($process)->not->toBeFalse();
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        expect($exit)->toBe(0, (string) $error);

        $result = json_decode((string) $output, true, flags: JSON_THROW_ON_ERROR);
        expect($result)->toBe([
            'rejected' => true,
            'afterFailure' => 0,
            'afterAbandonment' => false,
            'leftovers' => 0,
        ]);
    } finally {
        foreach (glob($directory.'/*') ?: [] as $leftover) {
            unlink($leftover);
        }
        rmdir($directory);
    }
});
