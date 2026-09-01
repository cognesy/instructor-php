<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Agents\Capability\Cancellation\InMemoryCancellationSource;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Tell\Data\TellAnswers;
use Cognesy\Tell\Data\TellRequest;
use Cognesy\Tell\Data\TellToolRequest;
use Cognesy\Tell\Tests\Support\RecordingDriver;
use Cognesy\Tell\Tests\Support\RequestRecorder;

it('discovers connection metadata and invokes one direct public SDK tool without inference', function (): void {
    $recorder = new RequestRecorder();
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(new RecordingDriver($recorder)));
    $project = tellLastTemporaryRoot() . '/project';
    mkdir($project, 0755, true);
    file_put_contents($project . '/evidence.txt', "direct evidence\n");
    $tell = tellTestOpen($project, $factory);

    $catalogue = $tell->catalogue()->connections();
    $models = $tell->catalogue()->models('openai');
    $result = $tell->tools()->dispatch(
        TellToolRequest::invoke('read_file', ['path' => 'evidence.txt']),
    );

    expect($catalogue['connections'])->not->toBeEmpty()
        ->and($catalogue['errors'])->toBeArray()
        ->and($models[0]['connection'])->toBe('openai')
        ->and($result->success)->toBeTrue()
        ->and($result->data['text'])->toContain('direct evidence')
        ->and($result->execution())->toBe(['mode' => 'direct', 'inference' => false, 'durable' => false])
        ->and($recorder->requests)->toBe([])
        ->and(is_dir($project . '/.tell'))->toBeFalse();
});

it('applies direct-tool policy and public cancellation without starting inference', function (): void {
    $cancellation = new InMemoryCancellationSource();
    $cancellation->cancel('deadline');
    $factory = tellTestFactory();
    $project = tellLastTemporaryRoot() . '/project';
    mkdir($project, 0755, true);

    $result = tellTestOpen($project, $factory, $cancellation)->tools()->dispatch(
        TellToolRequest::invoke('read_file', ['path' => 'missing.txt']),
    );

    expect($result->success)->toBeFalse()
        ->and($result->error['code'])->toBe('cancelled')
        ->and($result->execution()['inference'])->toBeFalse();
});

it('accepts queued answers and exposes a delegated child through public workspace branches', function (): void {
    $parent = FakeAgentDriver::fromSteps(
        ScenarioStep::toolCall('ask_user', ['question' => 'Deploy?', 'id' => 'deploy']),
        ScenarioStep::toolCall('spawn_subagent', ['subagent' => 'default', 'prompt' => 'Inspect the release.', 'context' => 'fresh']),
        ScenarioStep::final('parent completed'),
    );
    $child = FakeAgentDriver::fromSteps(ScenarioStep::final('child completed'));
    $build = 0;
    $factory = tellTestFactory(static function ($loop) use (&$build, $parent, $child) {
        return $loop->withDriver($build++ === 0 ? $parent : $child);
    });
    $project = tellLastTemporaryRoot() . '/project';
    mkdir($project, 0755, true);
    $tell = tellTestOpen($project, $factory);
    $tell->workspace()->initialize();
    $answers = new TellAnswers([
        ['id' => 'deploy', 'value' => 'yes', 'source' => 'sdk'],
    ]);

    $result = $tell->run(
        TellRequest::prompt('Decide and delegate.')
            ->durable()
            ->withAnswers($answers),
    );
    $children = array_values(array_filter(
        $tell->workspace()->branches()->list(full: true),
        static fn ($branch): bool => str_starts_with($branch->name, 'agent-'),
    ));

    expect($result->isCompleted())->toBeTrue()
        ->and($result->warnings())->toBe([])
        ->and($children)->toHaveCount(1)
        ->and($children[0]->head)->not->toBeNull()
        ->and($children[0]->created['source'])->toBe('agent');
});
