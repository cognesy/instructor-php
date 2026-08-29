<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Tell\Data\TellRequest;
use Cognesy\Tell\Tell;
use Cognesy\Tell\Workspace\Arena\Exception\ArenaIntegrityException;
use Cognesy\Tell\Workspace\Arena\RecordException;
use Cognesy\Tell\Workspace\WorkspaceException;

it('reads a named branch independently and keeps an immutable snapshot reproducible', function (): void {
    $project = tellTestProject();
    $tell = Tell::testing($project, 'recorded');
    $workspace = $tell->workspace();
    $workspace->initialize();

    $tell->run(TellRequest::prompt('Record baseline.')->durable());
    $workspace->branches()->create('release-review');
    $branch = $workspace->branch('release-review');
    $frozen = $branch->pin();
    $root = $branch->root();

    $tell->run(
        TellRequest::prompt('Advance only the review branch.')
            ->branch('release-review')
            ->durable(),
    );

    $branchHistory = $branch->history();
    $mainHistory = $workspace->branch('main')->history();
    $frozenHistory = $frozen->history();
    $reopened = $workspace->ref($frozen->hash())->transcript();

    expect($workspace->branches()->current()->name)->toBe('main')
        ->and($branchHistory->selector)->toMatchArray(['type' => 'branch', 'name' => 'release-review'])
        ->and($branchHistory->turns)->toHaveCount(2)
        ->and($mainHistory->turns)->toHaveCount(1)
        ->and($frozenHistory->selector)->toBe(['type' => 'ref', 'name' => $frozen->hash()])
        ->and($frozenHistory->turns)->toHaveCount(1)
        ->and($reopened->head)->toBe($frozen->hash())
        ->and($root->history()->turns)->toBe([]);
});

it('rejects missing branches and malformed or non-existent immutable refs', function (): void {
    $project = tellTestProject();
    $workspace = Tell::testing($project, 'unused')->workspace();
    $workspace->initialize();

    expect(fn () => $workspace->branch('missing'))
        ->toThrow(WorkspaceException::class, "branch 'missing' does not exist")
        ->and(fn () => $workspace->ref('not-a-hash'))
        ->toThrow(RecordException::class, 'lowercase SHA-256')
        ->and(fn () => $workspace->ref(str_repeat('a', 64)))
        ->toThrow(ArenaIntegrityException::class, 'object does not exist');
});
