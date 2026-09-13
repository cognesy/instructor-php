<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Tell\Composition\Standalone\StandaloneTellBuilder;
use Cognesy\Tell\Data\TellToolRequest;
it('dispatches direct tools through the standard Tell path', function (): void {
    $project = tellTestProject();
    file_put_contents($project . '/visible.txt', "bounded content\n");
    $paths = standardHostPaths($project);
    $tell = StandaloneTellBuilder::in($project, $paths)
        ->withDriverFactory(static fn () => FakeAgentDriver::fromResponses('unused'))
        ->build();

    $result = $tell->tools()->dispatch(
        TellToolRequest::invoke('read_file', ['path' => 'visible.txt'])->tools(['read_file']),
    );

    expect($result->success)->toBeTrue()
        ->and($result->execution())->toBe(['mode' => 'direct', 'inference' => false, 'durable' => false])
        ->and(json_encode($result->data, JSON_THROW_ON_ERROR))->toContain('bounded content');
});
