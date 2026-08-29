<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Tell\Capability\AskUser\AskUserTool;
use Cognesy\Tell\Capability\AskUser\TellAnswerQueue;
use Cognesy\Tell\Console\TellCommand;
use Cognesy\Tell\Data\TellRequest;
use Cognesy\Tell\Tell;
use Cognesy\Tell\Workspace\Arena\FilesystemArena;
use Symfony\Component\Console\Tester\CommandTester;

it('consumes ordered and exact-id answers once without ever prompting', function (): void {
    tellTestFactory(); // Register the standard isolated cleanup root for this focused tool test.
    $queue = new TellAnswerQueue([
        ['id' => null, 'value' => 'first', 'source' => 'cli'],
        ['id' => 'deploy', 'value' => 'yes', 'source' => 'file'],
    ]);
    $tool = new AskUserTool($queue);

    expect($tool->use(question: 'First?')->unwrap())->toMatchArray(['success' => true, 'answer' => 'first', 'source' => 'cli'])
        ->and($tool->use(question: 'Deploy?', id: 'deploy', choices: ['yes', 'no'])->unwrap())->toMatchArray(['success' => true, 'answer' => 'yes', 'source' => 'file'])
        ->and($tool->use(question: 'Again?')->unwrap())->toMatchArray([
            'success' => false,
            'answer' => null,
            'error' => ['code' => 'answer_unavailable', 'message' => 'No pre-supplied answer is available for this question.'],
        ]);
});

it('continues a deterministic agent tool call from a supplied answer without exposing it in events', function (): void {
    $canary = 'answer-canary-7d8e';
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(FakeAgentDriver::fromSteps(
        ScenarioStep::toolCall('ask_user', ['question' => 'Secret selection?']),
        ScenarioStep::final('continued after answer'),
    )));
    $tester = new CommandTester(new TellCommand($factory));

    $status = $tester->execute([
        'prompt' => 'Choose.',
        '--dir' => tellLastTemporaryRoot(),
        '--answer' => [$canary],
        '--output' => 'events',
    ]);
    $display = $tester->getDisplay();

    expect($status)->toBe(0)
        ->and($display)->toContain('tool.started')
        ->and($display)->toContain('tool.completed');
    expect($display)->not->toContain($canary);
});

it('loads bounded structured answers and reports extras without revealing their value', function (): void {
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(FakeAgentDriver::fromResponses('no question needed')));
    $directory = tellLastTemporaryRoot();
    $answers = $directory . '/answers.json';
    file_put_contents($answers, '[{"id":"choice","value":"file-canary"},"extra-canary"]');
    $tester = new CommandTester(new TellCommand($factory));

    $status = $tester->execute([
        'prompt' => 'No tool call.',
        '--dir' => $directory,
        '--answers-file' => $answers,
        '--output' => 'json',
    ]);
    $payload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

    expect($status)->toBe(0)
        ->and($payload['warnings'][0])->toBe('Unused non-interactive answers: 2.');
    expect(json_encode($payload, JSON_THROW_ON_ERROR))->not->toContain('file-canary')->not->toContain('extra-canary');
});

it('returns typed unavailable and invalid-choice outcomes immediately', function (): void {
    $unavailable = new AskUserTool(new TellAnswerQueue());
    $invalid = new AskUserTool(new TellAnswerQueue([
        ['id' => null, 'value' => 'maybe', 'source' => 'stdin'],
    ]));

    expect($unavailable->use(question: 'Nothing?')->unwrap()['error']['code'])->toBe('answer_unavailable')
        ->and($invalid->use(question: 'Choose?', choices: ['yes', 'no'])->unwrap()['error']['code'])->toBe('invalid_choice');
});

it('rejects duplicate, oversized, and conflicting supplied-answer inputs before execution', function (): void {
    $duplicate = static fn (): TellAnswerQueue => new TellAnswerQueue([
        ['id' => 'same', 'value' => 'one', 'source' => 'cli'],
        ['id' => 'same', 'value' => 'two', 'source' => 'cli'],
    ]);
    $oversized = static fn (): TellAnswerQueue => new TellAnswerQueue([
        ['id' => null, 'value' => str_repeat('x', TellAnswerQueue::MAX_ANSWER_BYTES + 1), 'source' => 'cli'],
    ]);
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(FakeAgentDriver::fromResponses('must not execute')));
    $directory = tellLastTemporaryRoot();
    $file = $directory . '/answers.json';
    file_put_contents($file, '[]');
    $tester = new CommandTester(new TellCommand($factory));

    expect($duplicate)->toThrow(InvalidArgumentException::class, 'unique non-empty')
        ->and($oversized)->toThrow(InvalidArgumentException::class, '8192 bytes')
        ->and($tester->execute([
            'prompt' => 'Do not execute.',
            '--dir' => $directory,
            '--answer' => ['yes'],
            '--answers-file' => $file,
        ]))->toBe(2)
        ->and($tester->getDisplay())->toContain('either repeatable --answer or exactly one structured answer source');
});

it('persists a durable semantic answer but never publishes a transient one', function (): void {
    $canary = 'canonical-answer-canary';
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(FakeAgentDriver::fromSteps(
        ScenarioStep::toolCall('ask_user', ['question' => 'Target?', 'id' => 'target']),
        ScenarioStep::final('durable continuation'),
    )));
    $project = tellLastTemporaryRoot() . '/project';
    mkdir($project, 0755, true);
    $workspace = $factory->workspace()->initialize($project)->workspace;
    $tell = Tell::open($project, $factory);

    $durable = $tell->run(
        TellRequest::prompt('Continue deliberately')
            ->durable()
            ->withAnswers(new TellAnswerQueue([
                ['id' => 'target', 'value' => $canary, 'source' => 'cli'],
            ])),
    );
    $afterDurable = (new FilesystemArena($workspace))->readRef()->toBytes();
    $transcript = $tell->workspace()->main()->transcript(full: true);

    $transientFactory = tellTestFactory(static fn ($loop) => $loop->withDriver(FakeAgentDriver::fromSteps(
        ScenarioStep::toolCall('ask_user', ['question' => 'Target?']),
        ScenarioStep::final('transient continuation'),
    )));
    $transientProject = tellLastTemporaryRoot() . '/transient-project';
    mkdir($transientProject, 0755, true);
    $transientWorkspace = $transientFactory->workspace()->initialize($transientProject)->workspace;
    $transient = Tell::open($transientProject, $transientFactory)->run(
        TellRequest::prompt('Inspect without publishing')
            ->transient()
            ->withAnswers(new TellAnswerQueue([
                ['id' => null, 'value' => $canary, 'source' => 'cli'],
            ])),
    );

    expect($durable->isCompleted())->toBeTrue()
        ->and(json_encode($transcript->messages, JSON_THROW_ON_ERROR))->toContain($canary)
        ->and($transient->isCompleted())->toBeTrue()
        ->and($transient->isTransient())->toBeTrue()
        ->and((new FilesystemArena($transientWorkspace))->readRef()->head)->toBeNull()
        ->and($afterDurable)->not->toBe('');
});
