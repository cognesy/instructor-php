<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Tests\Unit;

use Cognesy\InstructorHub\Services\StatusRepository;

it('persists status data with invalid UTF-8 output', function () {
    $tempDir = sys_get_temp_dir() . '/hub_status_' . uniqid();
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0777, true);
    }

    $repo = new StatusRepository($tempDir . '/status.json');

    $invalidOutput = "Invalid: \xC3\x28";

    $repo->save([
        'metadata' => [
            'totalExamples' => 1,
        ],
        'examples' => [
            [
                'index' => 1,
                'name' => 'ChatWithManyParticipants',
                'group' => 'B05_LLMExtras',
                'relativePath' => 'examples/B05_LLMExtras/ChatWithManyParticipants',
                'absolutePath' => $tempDir . '/run.php',
                'status' => 'error',
                'lastExecuted' => (new \DateTimeImmutable())->format('c'),
                'executionTime' => 0.1,
                'attempts' => 1,
                'errors' => [],
                'output' => $invalidOutput,
                'exitCode' => 1,
            ],
        ],
        'statistics' => [],
    ]);

    $loaded = $repo->load();

    expect($loaded['examples'][0]['output'] ?? null)->toBeString();
});
