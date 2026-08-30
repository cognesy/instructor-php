<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Tell\Workspace\FilesystemWorkspaceProvider;
use Cognesy\Tell\Contracts\CanManageTellWorkspace;
use Cognesy\Tell\Workspace\InMemoryWorkspaceModule;
use Cognesy\Tell\Workspace\WorkspaceRepository;

dataset('workspaceManagers', [
    'filesystem' => [static fn (): CanManageTellWorkspace => new FilesystemWorkspaceProvider(new WorkspaceRepository())],
    'memory' => [static fn (): CanManageTellWorkspace => new InMemoryWorkspaceModule()],
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
