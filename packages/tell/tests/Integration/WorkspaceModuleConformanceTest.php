<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Tell\Capability\Workspace\Filesystem\FilesystemTellWorkspaceProvider;
use Cognesy\Tell\Core\Contract\Workspace\CanProvideTellWorkspace;
use Cognesy\Tell\Capability\Workspace\Memory\InMemoryTellWorkspaceProvider;
use Cognesy\Tell\Capability\Workspace\Filesystem\WorkspaceRepository;
use Cognesy\Tell\Core\Workspace\Arena\Record\ConversationRoot;
use Cognesy\Tell\Core\Workspace\WorkspaceException;

dataset('workspaceManagers', [
    'filesystem' => [static fn (): CanProvideTellWorkspace => new FilesystemTellWorkspaceProvider(new WorkspaceRepository())],
    'memory' => [static fn (): CanProvideTellWorkspace => new InMemoryTellWorkspaceProvider()],
]);

it('shares workspace initialization, discovery, idempotency, and validation semantics', function (Closure $factory): void {
    $root = tellTestProject();
    mkdir($root . '/nested');
    $manager = $factory();

    expect($manager->discover($root))->toBeNull();
    $created = $manager->initialize($root);
    $again = $manager->initialize($root);
    $nested = $manager->discover($root . '/nested');
    $valid = $manager->validate($root . '/nested');

    expect($created->created)->toBeTrue()
        ->and($again->created)->toBeFalse()
        ->and($nested?->root)->toBe($created->root)
        ->and($valid->root)->toBe($created->root)
        ->and($valid->schema)->toBe($created->schema);
})->with('workspaceManagers');

it('shares opened arena selection and branch-configuration laws', function (Closure $factory): void {
    $root = tellTestProject();
    mkdir($root . '/nested');
    $provider = $factory();

    expect(fn () => $provider->open($root))->toThrow(WorkspaceException::class);
    $provider->initialize($root);
    $workspace = $provider->open($root . '/nested');
    $head = $workspace->arena->put(new ConversationRoot('shared-provider-law'));
    $workspace->arena->compareAndSwap('main', null, $head);
    $workspace->branchSelection->write('review');
    $main = $workspace->branchConfiguration->set('main', 'maxToolCalls', 7, 0);
    $projected = $provider->read($root, 'main');

    expect(fn () => $workspace->branchConfiguration->set('main', 'maxToolCalls', 8, 0))
        ->toThrow(WorkspaceException::class)
        ->and(fn () => $workspace->branchConfiguration->set('main', 'connection', 'token=secret', 1))
        ->toThrow(\InvalidArgumentException::class);

    $workspace->branchConfiguration->inherit('main', 'review');
    $reopened = $provider->open($root);

    expect($main)->toBe(['version' => 1, 'values' => ['maxToolCalls' => 7]])
        ->and($projected?->values)->toBe(['maxToolCalls' => 7])
        ->and($reopened->arena->readRef('main')->head?->equals($head))->toBeTrue()
        ->and($reopened->branchSelection->read()->branch)->toBe('review')
        ->and($reopened->branchConfiguration->read('review'))->toBe([
            'version' => 1,
            'values' => ['maxToolCalls' => 7],
        ])
        ->and($reopened->branchConfiguration->effective('main')['values']['maxToolCalls'])->toBe(7);
})->with('workspaceManagers');
