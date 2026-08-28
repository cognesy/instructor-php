<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/Pest.php';

use Cognesy\Tell\Canonical\CanonicalConversationRoot;
use Cognesy\Tell\Canonical\CanonicalLineage;
use Cognesy\Tell\Canonical\CanonicalMessage;
use Cognesy\Tell\Canonical\CanonicalRole;
use Cognesy\Tell\Canonical\CanonicalTextPart;
use Cognesy\Tell\Canonical\CanonicalTurn;
use Cognesy\Tell\Command\BranchCommand;
use Cognesy\Tell\Command\CheckoutCommand;
use Cognesy\Tell\Command\ConfigCommand;
use Cognesy\Tell\Command\ResetCommand;
use Cognesy\Tell\Runtime\TellAgentFactory;
use Cognesy\Tell\Runtime\TellOptions;
use Cognesy\Tell\TellCommand;
use Cognesy\Tell\Workspace\ArenaStore;
use Cognesy\Tell\Workspace\BranchConfigStore;
use Cognesy\Tell\Workspace\TellWorkspace;
use Symfony\Component\Console\Tester\CommandTester;

it('creates independent refs from main, another branch, and empty state without copying objects', function (): void {
    $factory = tellTestFactory();
    $project = tellBranchProject($factory);
    $workspace = tellBranchWorkspace($factory, $project);
    $arena = new ArenaStore($workspace);
    $head = $arena->put(new CanonicalConversationRoot(
        'branch-main',
        [new CanonicalMessage(CanonicalRole::User, [new CanonicalTextPart('shared history')])],
    ));
    $arena->compareAndSwap('main', null, $head);
    $objectSnapshot = tellBranchSnapshot($workspace->paths->objects);
    $command = new BranchCommand($factory);

    $current = new CommandTester($command);
    $fromBranch = new CommandTester($command);
    $empty = new CommandTester($command);
    expect($current->execute(['action' => 'create', 'name' => 'review', '--dir' => $project, '--json' => true]))->toBe(0)
        ->and($fromBranch->execute(['action' => 'create', 'name' => 'followup', '--from' => 'review', '--dir' => $project, '--json' => true]))->toBe(0)
        ->and($empty->execute(['action' => 'create', 'name' => 'scratch', '--empty' => true, '--dir' => $project, '--json' => true]))->toBe(0);

    $currentPayload = json_decode($current->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
    $fromPayload = json_decode($fromBranch->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
    $emptyPayload = json_decode($empty->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

    expect($currentPayload)->toMatchArray([
        'name' => 'review',
        'head' => $head->toString(),
        'empty' => false,
        'created' => true,
        'source' => ['source' => 'current', 'branch' => 'main', 'head' => $head->toString()],
    ])
        ->and($fromPayload['source'])->toBe(['source' => 'branch', 'branch' => 'review', 'head' => $head->toString()])
        ->and($emptyPayload)->toMatchArray([
            'name' => 'scratch',
            'head' => null,
            'empty' => true,
            'source' => ['source' => 'empty', 'branch' => null, 'head' => null],
        ])
        ->and($arena->readRef('branches/review')->head?->equals($head))->toBeTrue()
        ->and($arena->readRef('branches/followup')->head?->equals($head))->toBeTrue()
        ->and($arena->readRef('branches/scratch')->head)->toBeNull()
        ->and(tellBranchSnapshot($workspace->paths->objects))->toBe($objectSnapshot);
});

it('lists and shows deterministic verified branch views without inference or writes', function (): void {
    $factory = tellTestFactory(static function (): never {
        throw new RuntimeException('Branch inspection must not construct an agent loop.');
    });
    $project = tellBranchProject($factory);
    $workspace = tellBranchWorkspace($factory, $project);
    $arena = new ArenaStore($workspace);
    $head = $arena->put(new CanonicalConversationRoot('branch-list'));
    $arena->compareAndSwap('main', null, $head);
    $command = new BranchCommand($factory);
    (new CommandTester($command))->execute(['action' => 'create', 'name' => 'zeta', '--dir' => $project]);
    (new CommandTester($command))->execute(['action' => 'create', 'name' => 'alpha', '--empty' => true, '--dir' => $project]);
    $before = tellBranchSnapshot($workspace->paths->arena);

    $list = new CommandTester($command);
    $show = new CommandTester($command);
    expect($list->execute(['action' => 'list', '--dir' => $project, '--json' => true]))->toBe(0)
        ->and($show->execute(['action' => 'show', 'name' => 'zeta', '--dir' => $project, '--json' => true]))->toBe(0);
    $listPayload = json_decode($list->getDisplay(), true, flags: JSON_THROW_ON_ERROR);
    $showPayload = json_decode($show->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

    expect(array_column($listPayload['branches'], 'name'))->toBe(['alpha', 'zeta'])
        ->and($listPayload['branches'][0])->toMatchArray([
            'name' => 'alpha',
            'head' => null,
            'empty' => true,
            'turnCount' => 0,
            'configuration' => ['status' => 'default'],
        ])
        ->and(array_key_exists('created', $listPayload['branches'][0]))->toBeFalse()
        ->and($showPayload)->toMatchArray([
            'name' => 'zeta',
            'head' => $head->toString(),
            'turnCount' => 0,
            'created' => ['source' => 'current', 'branch' => 'main', 'head' => $head->toString()],
        ])
        ->and(tellBranchSnapshot($workspace->paths->arena))->toBe($before);
});

it('rejects invalid conflicting and missing branch operations without mutation', function (): void {
    $factory = tellTestFactory();
    $project = tellBranchProject($factory);
    $workspace = tellBranchWorkspace($factory, $project);
    $command = new BranchCommand($factory);
    $create = new CommandTester($command);
    expect($create->execute(['action' => 'create', 'name' => 'review', '--empty' => true, '--dir' => $project, '--json' => true]))->toBe(0);
    $before = tellBranchSnapshot($workspace->paths->arena);

    foreach ([
        [['action' => 'create', 'name' => 'Review', '--dir' => $project, '--json' => true], 2],
        [['action' => 'create', 'name' => '../review', '--dir' => $project, '--json' => true], 2],
        [['action' => 'create', 'name' => 'main', '--dir' => $project, '--json' => true], 2],
        [['action' => 'create', 'name' => str_repeat('a', 65), '--dir' => $project, '--json' => true], 2],
        [['action' => 'create', 'name' => 'review', '--dir' => $project, '--json' => true], 1],
        [['action' => 'show', 'name' => 'missing', '--dir' => $project, '--json' => true], 2],
        [['action' => 'create', 'name' => 'other', '--from' => 'missing', '--dir' => $project, '--json' => true], 2],
        [['action' => 'create', 'name' => 'conflict', '--from' => 'review', '--empty' => true, '--dir' => $project, '--json' => true], 2],
    ] as [$arguments, $status]) {
        $tester = new CommandTester($command);
        expect($tester->execute($arguments))->toBe($status)
            ->and(tellBranchSnapshot($workspace->paths->arena))->toBe($before);
    }
});

it('fails show when the referenced canonical head is corrupt or dangling', function (): void {
    $factory = tellTestFactory();
    $project = tellBranchProject($factory);
    $workspace = tellBranchWorkspace($factory, $project);
    $arena = new ArenaStore($workspace);
    $head = $arena->put(new CanonicalConversationRoot('branch-corrupt'));
    $arena->compareAndSwap('main', null, $head);
    (new CommandTester(new BranchCommand($factory)))->execute(['action' => 'create', 'name' => 'review', '--dir' => $project]);
    file_put_contents($arena->objectPath($head), '{"truncated"');
    $before = tellBranchSnapshot($workspace->paths->arena);

    $tester = new CommandTester(new BranchCommand($factory));
    expect($tester->execute(['action' => 'show', 'name' => 'review', '--dir' => $project, '--json' => true]))->toBe(1)
        ->and(json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR)['error'])->toContain('object bytes do not match')
        ->and(tellBranchSnapshot($workspace->paths->arena))->toBe($before);
});

it('persists checkout while an invocation-local branch resolution leaves it unchanged', function (): void {
    $factory = tellTestFactory();
    $project = tellBranchProject($factory);
    $workspace = tellBranchWorkspace($factory, $project);
    $arena = new ArenaStore($workspace);
    $head = $arena->put(new CanonicalConversationRoot('checkout'));
    $arena->compareAndSwap('main', null, $head);
    (new CommandTester(new BranchCommand($factory)))->execute(['action' => 'create', 'name' => 'review', '--dir' => $project]);

    $tester = new CommandTester(new CheckoutCommand($factory));
    expect($tester->execute(['name' => 'review', '--dir' => $project, '--json' => true]))->toBe(0)
        ->and(json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR))->toMatchArray([
            'previous' => ['name' => 'main', 'source' => 'current'],
            'branch' => ['name' => 'review', 'source' => 'current'],
            'changed' => true,
        ]);

    $reopened = $factory->workspace()->discover($project);
    expect($reopened)->not->toBeNull();
    $store = new ArenaStore($reopened);
    expect($store->readCurrentBranch()->branch)->toBe('review')
        ->and((new \Cognesy\Tell\Workspace\BranchResolver($store))->resolve('main')->toArray())->toBe([
            'name' => 'main', 'source' => 'invocation',
        ])
        ->and($store->readCurrentBranch()->branch)->toBe('review');
});

it('reports current or invocation-local branch selection in terminal output and rejects session ambiguity before inference', function (): void {
    $factory = tellTestFactory(static fn ($loop) => $loop->withDriver(\Cognesy\Agents\Drivers\Testing\FakeAgentDriver::fromResponses('branch answer', 'current answer')));
    $project = tellBranchProject($factory);
    (new CommandTester(new BranchCommand($factory)))->execute(['action' => 'create', 'name' => 'review', '--empty' => true, '--dir' => $project]);

    $invocation = new CommandTester(new TellCommand($factory));
    expect($invocation->execute(['prompt' => 'on review', '--branch' => 'review', '--dir' => $project, '--output' => 'json']))->toBe(0)
        ->and(json_decode($invocation->getDisplay(), true, flags: JSON_THROW_ON_ERROR)['branch'])->toBe(['name' => 'review', 'source' => 'invocation']);

    expect((new CommandTester(new CheckoutCommand($factory)))->execute(['name' => 'review', '--dir' => $project]))->toBe(0);
    $current = new CommandTester(new TellCommand($factory));
    expect($current->execute(['prompt' => 'on current', '--dir' => $project, '--output' => 'json']))->toBe(0)
        ->and(json_decode($current->getDisplay(), true, flags: JSON_THROW_ON_ERROR)['branch'])->toBe(['name' => 'review', 'source' => 'current']);

    $noInference = tellTestFactory(static function (): never {
        throw new RuntimeException('Invalid selection must fail before agent construction.');
    });
    $invalid = new CommandTester(new TellCommand($noInference));
    expect($invalid->execute(['prompt' => 'ambiguous', '--branch' => 'review', '--session' => 'legacy', '--dir' => $project, '--output' => 'json']))->toBe(2)
        ->and(json_decode($invalid->getDisplay(), true, flags: JSON_THROW_ON_ERROR)['error'])->toContain('--branch and --session cannot be used together');
});

it('resets only the selected branch ref and retains immutable objects', function (): void {
    $factory = tellTestFactory();
    $project = tellBranchProject($factory);
    $workspace = tellBranchWorkspace($factory, $project);
    $arena = new ArenaStore($workspace);
    $head = $arena->put(new CanonicalConversationRoot('reset-root'));
    $arena->compareAndSwap('main', null, $head);
    (new CommandTester(new BranchCommand($factory)))->execute(['action' => 'create', 'name' => 'review', '--dir' => $project]);
    $before = tellBranchSnapshot($workspace->paths->objects);

    $tester = new CommandTester(new ResetCommand($factory));
    expect($tester->execute(['--branch' => 'review', '--steps' => '1', '--dir' => $project, '--json' => true]))->toBe(0);
    $payload = json_decode($tester->getDisplay(), true, flags: JSON_THROW_ON_ERROR);

    expect($payload)->toMatchArray(['branch' => ['name' => 'review', 'source' => 'invocation'], 'previousHead' => $head->toString(), 'head' => null, 'distance' => 1, 'changed' => true])
        ->and($arena->readRef('main')->head?->toString())->toBe($head->toString())
        ->and($arena->readRef('branches/review')->head)->toBeNull()
        ->and(tellBranchSnapshot($workspace->paths->objects))->toBe($before);
});

it('resets only verified reachable ancestors and leaves invalid targets untouched', function (): void {
    $factory = tellTestFactory();
    $project = tellBranchProject($factory);
    $workspace = tellBranchWorkspace($factory, $project);
    $arena = new ArenaStore($workspace);
    $root = $arena->put(new CanonicalConversationRoot('reset-history'));
    $first = $arena->put(new CanonicalTurn(
        'reset-first',
        new CanonicalLineage($root),
        [new CanonicalMessage(CanonicalRole::User, [new CanonicalTextPart('first')])],
    ));
    $second = $arena->put(new CanonicalTurn(
        'reset-second',
        new CanonicalLineage($root, $first),
        [new CanonicalMessage(CanonicalRole::User, [new CanonicalTextPart('second')])],
    ));
    $arena->compareAndSwap('main', null, $second);
    (new CommandTester(new BranchCommand($factory)))->execute(['action' => 'create', 'name' => 'review', '--dir' => $project]);
    $objects = tellBranchSnapshot($workspace->paths->objects);

    $reset = new CommandTester(new ResetCommand($factory));
    expect($reset->execute(['--branch' => 'review', '--to' => $first->toString(), '--dir' => $project, '--json' => true]))->toBe(0)
        ->and(json_decode($reset->getDisplay(), true, flags: JSON_THROW_ON_ERROR))->toMatchArray([
            'previousHead' => $second->toString(),
            'head' => $first->toString(),
            'distance' => 1,
            'changed' => true,
        ])
        ->and($arena->readRef('main')->head?->toString())->toBe($second->toString())
        ->and(tellBranchSnapshot($workspace->paths->objects))->toBe($objects);

    $unrelated = $arena->put(new CanonicalConversationRoot('unrelated'));
    $descendant = $arena->put(new CanonicalTurn(
        'reset-descendant',
        new CanonicalLineage($root, $second),
        [new CanonicalMessage(CanonicalRole::User, [new CanonicalTextPart('future')])],
    ));
    $before = $arena->readRef('branches/review')->toBytes();
    $noOp = new CommandTester(new ResetCommand($factory));
    expect($noOp->execute(['--branch' => 'review', '--to' => $first->toString(), '--dir' => $project, '--json' => true]))->toBe(0)
        ->and(json_decode($noOp->getDisplay(), true, flags: JSON_THROW_ON_ERROR)['changed'])->toBeFalse()
        ->and($arena->readRef('branches/review')->toBytes())->toBe($before);
    foreach ([
        ['--to' => $unrelated->toString()],
        ['--to' => $descendant->toString()],
        ['--to' => str_repeat('a', 64)],
        ['--steps' => '0'],
        ['--steps' => '1001'],
        ['--steps' => '1', '--to' => $first->toString()],
    ] as $arguments) {
        $tester = new CommandTester(new ResetCommand($factory));
        expect($tester->execute($arguments + ['--branch' => 'review', '--dir' => $project, '--json' => true]))->toBe(2)
            ->and($arena->readRef('branches/review')->toBytes())->toBe($before);
    }
});

it('inherits branch config by value, exposes effective provenance, and keeps branches independent', function (): void {
    $factory = tellTestFactory();
    $project = tellBranchProject($factory);
    $workspace = tellBranchWorkspace($factory, $project);
    $branch = new CommandTester(new BranchCommand($factory));
    $command = new ConfigCommand($factory);
    $set = new CommandTester($command);
    expect($set->execute(['action' => 'set', 'key' => 'model', 'value' => '"deepseek-v4-flash"', '--if-version' => '0', '--dir' => $project, '--json' => true]))->toBe(0)
        ->and($branch->execute(['action' => 'create', 'name' => 'review', '--dir' => $project, '--json' => true]))->toBe(0);

    $show = new CommandTester($command);
    expect($show->execute(['action' => 'show', '--branch' => 'review', '--dir' => $project, '--json' => true]))->toBe(0)
        ->and(json_decode($show->getDisplay(), true, flags: JSON_THROW_ON_ERROR))->toMatchArray([
            'version' => 1,
            'values' => ['model' => 'deepseek-v4-flash'],
            'allowedKeys' => ['connection', 'model', 'reasoningEffort', 'output', 'tools', 'maxRetries', 'timeoutMs', 'maxOutputChars', 'maxToolOutputChars', 'maxToolCalls'],
        ]);

    $review = new CommandTester($command);
    expect($review->execute(['action' => 'set', 'key' => 'model', 'value' => '"deepseek-v4-pro"', '--if-version' => '1', '--branch' => 'review', '--dir' => $project, '--json' => true]))->toBe(0);
    $main = new CommandTester($command);
    expect($main->execute(['action' => 'get', 'key' => 'model', '--dir' => $project, '--json' => true]))->toBe(0)
        ->and(json_decode($main->getDisplay(), true, flags: JSON_THROW_ON_ERROR))->toMatchArray(['value' => 'deepseek-v4-flash', 'source' => 'branch']);

    $effective = new CommandTester($command);
    expect($effective->execute(['action' => 'effective', '--branch' => 'review', '--dir' => $project, '--json' => true]))->toBe(0)
        ->and(json_decode($effective->getDisplay(), true, flags: JSON_THROW_ON_ERROR))->toMatchArray([
            'values' => [
                'connection' => 'openai',
                'model' => 'deepseek-v4-pro',
                'output' => 'toon',
                'tools' => [],
                'maxRetries' => 0,
                'timeoutMs' => 30_000,
                'maxOutputChars' => 200_000,
                'maxToolOutputChars' => 40_000,
                'maxToolCalls' => 100,
            ],
            'provenance' => [
                'connection' => 'bundled',
                'model' => 'branch',
                'output' => 'bundled',
                'tools' => 'bundled',
                'maxRetries' => 'bundled',
                'timeoutMs' => 'bundled',
                'maxOutputChars' => 'bundled',
                'maxToolOutputChars' => 'bundled',
                'maxToolCalls' => 'bundled',
            ],
            'precedence' => ['cli', 'branch', 'project', 'user', 'bundled'],
        ])
        ->and((new BranchConfigStore($factory->workspace()->discover($project) ?? throw new RuntimeException('workspace missing')))->read('review')['values']['model'])->toBe('deepseek-v4-pro')
        ->and((new BranchConfigStore($workspace))->read('main')['values']['model'])->toBe('deepseek-v4-flash');
});

it('rejects unsafe and invalid branch configuration without leaking secrets or partial writes', function (): void {
    $factory = tellTestFactory();
    $project = tellBranchProject($factory);
    $command = new ConfigCommand($factory);
    $set = new CommandTester($command);
    expect($set->execute(['action' => 'set', 'key' => 'tools', 'value' => '["tell.coding"]', '--if-version' => '0', '--dir' => $project, '--json' => true]))->toBe(0);
    $before = (new BranchConfigStore(tellBranchWorkspace($factory, $project)))->read('main');

    $bad = new CommandTester($command);
    foreach ([
        ['key' => 'connection', 'value' => '"sk-secret-canary"'],
        ['key' => 'connection', 'value' => '"https://user:password@example.test"'],
        ['key' => 'maxRetries', 'value' => '"three"'],
        ['key' => 'timeoutMs', 'value' => '0'],
        ['key' => 'unknown', 'value' => '"value"'],
    ] as $arguments) {
        expect($bad->execute(['action' => 'set', '--if-version' => '1', '--dir' => $project, '--json' => true] + $arguments))->toBe(2)
            ->and($bad->getDisplay())->not->toContain('sk-secret-canary')
            ->and((new BranchConfigStore(tellBranchWorkspace($factory, $project)))->read('main'))->toBe($before);
    }
    $stale = new CommandTester($command);
    expect($stale->execute(['action' => 'delete', 'key' => 'tools', '--if-version' => '0', '--dir' => $project, '--json' => true]))->toBe(1)
        ->and((new BranchConfigStore(tellBranchWorkspace($factory, $project)))->read('main'))->toBe($before);
});

it('applies branch runtime intent only where an invocation did not explicitly override it', function (): void {
    $factory = tellTestFactory();
    $project = tellBranchProject($factory);
    $intent = ['connection' => 'ollama', 'model' => 'branch-model', 'tools' => ['tell.coding']];

    $branch = (new TellOptions(prompt: 'branch intent', directory: $project))->withBranchConfig($intent);
    $cli = (new TellOptions(
        prompt: 'CLI intent',
        directory: $project,
        connection: 'openai',
        model: 'cli-model',
        tools: ['tell.self_knowledge'],
        connectionExplicit: true,
        modelExplicit: true,
        toolsExplicit: true,
    ))->withBranchConfig($intent);

    expect($branch->connection)->toBe('ollama')
        ->and($branch->model)->toBe('branch-model')
        ->and($branch->tools)->toBe(['tell.coding'])
        ->and($cli->connection)->toBe('openai')
        ->and($cli->model)->toBe('cli-model')
        ->and($cli->tools)->toBe(['tell.self_knowledge']);
});

it('persists every allowed typed branch config value through a fresh workspace reader', function (): void {
    $factory = tellTestFactory();
    $project = tellBranchProject($factory);
    $config = new BranchConfigStore(tellBranchWorkspace($factory, $project));
    $values = [
        'connection' => 'ollama',
        'model' => 'local-model',
        'reasoningEffort' => 'medium',
        'tools' => ['tell.coding', 'tell.self_knowledge'],
        'maxRetries' => 2,
        'timeoutMs' => 1_500,
        'maxOutputChars' => 4_096,
        'maxToolOutputChars' => 2_048,
        'maxToolCalls' => 7,
    ];
    $version = 0;
    foreach ($values as $key => $value) {
        $version = $config->set('main', $key, $value, $version)['version'];
    }
    ksort($values, SORT_STRING);
    $fresh = new BranchConfigStore($factory->workspace()->discover($project) ?? throw new RuntimeException('workspace missing'));

    expect($fresh->read('main'))->toBe(['version' => 9, 'values' => $values]);
});

function tellBranchProject(TellAgentFactory $factory): string
{
    $project = tellLastTemporaryRoot().'/branch-workspace';
    mkdir($project, 0700, true);
    $factory->workspace()->initialize($project);

    return $project;
}

function tellBranchWorkspace(TellAgentFactory $factory, string $project): TellWorkspace
{
    $workspace = $factory->workspace()->discover($project);
    if ($workspace === null) {
        throw new RuntimeException('Expected initialized Tell workspace.');
    }

    return $workspace;
}

/** @return array<string, string> */
function tellBranchSnapshot(string $directory): array
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
    );
    $snapshot = [];
    foreach ($iterator as $file) {
        if (! $file->isFile()) {
            continue;
        }
        $path = $file->getPathname();
        $snapshot[substr($path, strlen($directory) + 1)] = (string) file_get_contents($path);
    }
    ksort($snapshot, SORT_STRING);

    return $snapshot;
}
