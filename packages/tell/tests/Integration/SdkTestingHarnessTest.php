<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Tell\Data\TellRequest;
use Cognesy\Tell\Tell;
use Cognesy\Tell\Testing\TellTestFactory;

it('runs deterministic SDK responses without external provider credentials', function (): void {
    $project = tellTestProject();
    $previous = getenv('OPENAI_API_KEY');
    putenv('OPENAI_API_KEY');

    try {
        $result = Tell::testing($project, 'deterministic answer')->run(
            TellRequest::prompt('This prompt never leaves the process.'),
        );

        expect($result->isCompleted())->toBeTrue()
            ->and(trim($result->text()))->toBe('deterministic answer');
    } finally {
        match (is_string($previous)) {
            true => putenv('OPENAI_API_KEY=' . $previous),
            false => putenv('OPENAI_API_KEY'),
        };
    }
});

it('scripts multi-step and terminal failure scenarios', function (): void {
    $project = tellTestProject();
    file_put_contents($project . '/fixture.txt', "fixture\n");

    $completed = TellTestFactory::steps(
        ScenarioStep::toolCall('read_file', ['path' => 'fixture.txt'], 'working'),
        ScenarioStep::final('finished'),
    )->open($project)->run(
        TellRequest::prompt('Perform two deterministic steps.')->maxSteps(2),
    );
    $failed = TellTestFactory::steps(
        ScenarioStep::error('expected failure'),
    )->open($project)->run(
        TellRequest::prompt('Exercise a terminal failure.'),
    );

    expect($completed->isCompleted())->toBeTrue()
        ->and(trim($completed->text()))->toBe('finished')
        ->and($failed->status())->toBe(ExecutionStatus::Failed)
        ->and($failed->isCompleted())->toBeFalse();
});
