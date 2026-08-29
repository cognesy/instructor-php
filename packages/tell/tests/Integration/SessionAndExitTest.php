<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Agents\Session\Data\SessionId;
use Cognesy\Tell\Command\SessionsCommand;
use Cognesy\Tell\Console\TellCommand;
use Cognesy\Tell\Tests\Support\RecordingDriver;
use Cognesy\Tell\Tests\Support\RequestRecorder;
use Cognesy\Tell\Workspace\Arena\FilesystemArena;
use Cognesy\Tell\Workspace\Session\SessionRef;
use HelgeSverre\Toon\Toon;
use Symfony\Component\Console\Tester\CommandTester;

it('continues a named session with its prior messages in the next compiled request', function (): void {
    $recorder = new RequestRecorder();
    $driver = new RecordingDriver($recorder);
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver($driver));
    $command = new TellCommand($factory);
    $project = tellLastTemporaryRoot() . '/named-session-project';
    mkdir($project, 0755, true);
    $workspace = $factory->workspace()->initialize($project)->workspace;

    (new CommandTester($command))->execute(['prompt' => 'first turn', '--session' => 's1', '--dir' => $project]);
    (new CommandTester($command))->execute(['prompt' => 'second turn', '--session' => 's1', '--dir' => $project]);

    $sessionRef = new SessionRef(SessionId::from('s1'));
    $secondRequest = array_map(
        static fn (array $message): array => [
            'role' => $message['role'],
            'content' => $message['content'],
        ],
        $recorder->requests[1],
    );
    expect((new FilesystemArena($workspace))->readRef($sessionRef->refName())->head)->not->toBeNull()
        ->and($recorder->requests)->toHaveCount(2)
        ->and($secondRequest)->toContain(['role' => 'user', 'content' => 'first turn'])
        ->toContain(['role' => 'assistant', 'content' => 'recorded answer'])
        ->toContain(['role' => 'user', 'content' => 'second turn']);
});

it('does not create session storage without the session option', function (): void {
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(FakeAgentDriver::fromResponses('done')));
    (new CommandTester(new TellCommand($factory)))->execute(['prompt' => 'stateless']);

    expect(is_dir($factory->paths()->sessions))->toBeFalse();
});

it('bounds session detail until full content is requested', function (): void {
    $prompt = str_repeat('long message ', 120);
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(FakeAgentDriver::fromResponses('done')));
    $project = tellLastTemporaryRoot() . '/long-session-project';
    mkdir($project, 0755, true);
    $factory->workspace()->initialize($project);
    (new CommandTester(new TellCommand($factory)))->execute([
        'prompt' => $prompt,
        '--session' => 'long-session',
        '--dir' => $project,
    ]);

    $summary = new CommandTester(new SessionsCommand($factory));
    $summary->execute(['action' => 'show', 'id' => 'long-session', '--dir' => $project]);
    $summaryPayload = Toon::decode($summary->getDisplay());
    $full = new CommandTester(new SessionsCommand($factory));
    $full->execute(['action' => 'show', 'id' => 'long-session', '--full' => true, '--dir' => $project]);
    $fullPayload = Toon::decode($full->getDisplay());

    expect($summaryPayload['truncated'])->toBeTrue()
        ->and($summaryPayload['messages'])->toEndWith('...')
        ->and($summaryPayload['help'][0])->toContain('--full')
        ->and($fullPayload['truncated'])->toBeFalse()
        ->and($fullPayload['messages'])->toContain($prompt);
});

it('returns stable success, failure, and stopped exit codes', function (FakeAgentDriver $driver, int $expected): void {
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver($driver));
    $tester = new CommandTester(new TellCommand($factory));
    $status = $tester->execute(['prompt' => 'status', '--max-steps' => '1']);

    expect($status)->toBe($expected);
})->with([
    'success' => [FakeAgentDriver::fromResponses('done'), 0],
    'failure' => [new FakeAgentDriver([ScenarioStep::error('failed')]), 1],
    'stopped without final answer' => [new FakeAgentDriver([ScenarioStep::tool('')]), 1],
]);

it('references no agents classes annotated internal', function (): void {
    $agentsRoot = dirname((new ReflectionClass(AgentLoop::class))->getFileName());
    $tellRoot = dirname(__DIR__, 2) . '/src';
    $internal = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($agentsRoot));
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $source = file_get_contents($file->getPathname());
        if (!is_string($source) || !str_contains($source, '@internal')) {
            continue;
        }
        if (preg_match('/namespace\s+([^;]+);.*?(?:class|interface|trait|enum)\s+(\w+)/s', $source, $match) === 1) {
            $internal[] = $match[1] . '\\' . $match[2];
        }
    }
    $tellSource = '';
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tellRoot)) as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $tellSource .= file_get_contents($file->getPathname());
        }
    }

    foreach ($internal as $class) {
        expect(str_contains($tellSource, $class))->toBeFalse();
    }
    expect($internal === [])->toBeFalse();
});
