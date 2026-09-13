<?php declare(strict_types=1);

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

it('isolates model facts across eight workers and one thousand executions', function (string $scopeMode, int $scopeCount) {
    $root = sys_get_temp_dir() . '/instructor-model-concurrency-' . bin2hex(random_bytes(8));
    mkdir($root . '/shared/custom', 0700, true);
    try {
        foreach (range(1, 1000) as $index) {
            file_put_contents($root . '/shared/custom/unrelated-' . $index . '.yaml', 'invalid: [');
        }
        $autoload = match (true) {
            is_file(dirname(__DIR__, 3) . '/vendor/autoload.php') => dirname(__DIR__, 3) . '/vendor/autoload.php',
            default => dirname(__DIR__, 5) . '/vendor/autoload.php',
        };
        $workers = [];
        $started = hrtime(true);
        foreach (range(1, 8) as $index) {
            mkdir($root . '/worker-' . $index . '/custom', 0700, true);
            file_put_contents($root . '/worker-' . $index . '/custom/selected.yaml', Yaml::dump([
                'schemaVersion' => 1,
                'version' => 'fixture-v1',
                'profile' => ['driver' => 'custom', 'model' => 'selected', 'source' => 'worker-' . $index],
            ]));
            $worker = new Process([
                PHP_BINARY, dirname(__DIR__, 2) . '/Fixtures/model-record-worker.php',
                $autoload,
                $root . '/worker-' . $index, $root . '/shared',
                $scopeMode,
            ]);
            $worker->start();
            $workers[$index] = $worker;
        }
        $durations = [];
        $peakBytes = 0;
        foreach ($workers as $index => $worker) {
            $worker->wait();
            expect($worker->isSuccessful())->toBeTrue($worker->getErrorOutput());
            $result = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            expect($result['requests'])->toBe(125)
                ->and($result['scopes'])->toBe($scopeCount)
                ->and($result['source'])->toBe('worker-' . $index);
            $durations = [...$durations, ...$result['durationsMs']];
            $peakBytes = max($peakBytes, $result['peakBytes']);
        }
        sort($durations, SORT_NUMERIC);
        expect($durations)->toHaveCount(1000);
        fwrite(STDOUT, sprintf(
            "\n[model-records] scope=%s, 8 workers, 1000 executions, 1000 unrelated records; wall=%.1fms; p50=%.3fms p95=%.3fms p99=%.3fms peak=%d bytes\n",
            $scopeMode, (hrtime(true) - $started) / 1_000_000,
            $durations[499], $durations[949], $durations[989], $peakBytes,
        ));
    } finally {
        (new Filesystem)->remove($root);
    }
})->with([
    'repeated objects in scripts' => ['script', 1],
    'independent cold request scopes' => ['request', 125],
]);
