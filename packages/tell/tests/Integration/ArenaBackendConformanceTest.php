<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Tell\Core\Contract\Workspace\CanUseTellWorkspaceArena;
use Cognesy\Tell\Core\Workspace\Arena\Exception\ArenaIntegrityException;
use Cognesy\Tell\Core\Workspace\Arena\Exception\RefConflict;
use Cognesy\Tell\Capability\Workspace\Filesystem\FilesystemArena;
use Cognesy\Tell\Capability\Workspace\Memory\InMemoryArena;
use Cognesy\Tell\Core\Workspace\Arena\ObjectHash;
use Cognesy\Tell\Core\Workspace\Arena\Record\ConversationRoot;
use Cognesy\Tell\Core\Workspace\Arena\Record\Message as RecordMessage;
use Cognesy\Tell\Core\Workspace\Arena\Record\Role;
use Cognesy\Tell\Core\Workspace\Arena\Record\TextPart;

dataset('arenaBackends', [
    'filesystem' => [static function (): CanUseTellWorkspaceArena {
        $project = tellTestProject();
        $workspace = tellTestWorkspaces()->initialize($project)->workspace;

        return new FilesystemArena($workspace);
    }],
    'memory' => [static fn (): CanUseTellWorkspaceArena => new InMemoryArena()],
]);

it('shares content addressing, generic refs, CAS, and failure atomicity', function (Closure $factory): void {
    $arena = $factory();
    $root = new ConversationRoot('conformance-root', [
        new RecordMessage(Role::User, [new TextPart('preserve this')]),
    ]);
    $head = $arena->put($root);

    expect($arena->exists($head))->toBeTrue()
        ->and($arena->get($head)->toArray())->toBe($root->toArray());

    $published = $arena->compareAndSwap('main', null, $head);
    $arena->createRef('branches/review', $published);

    expect($arena->readRef()->head?->toString())->toBe($head->toString())
        ->and($arena->readOptionalRef('branches/review')?->head?->toString())->toBe($head->toString())
        ->and($arena->refNames('branches'))->toBe(['branches/review']);

    $missing = new ObjectHash(str_repeat('a', 64));
    expect(fn () => $arena->compareAndSwap('main', $head, $missing))->toThrow(ArenaIntegrityException::class)
        ->and($arena->readRef()->head?->toString())->toBe($head->toString())
        ->and(fn () => $arena->compareAndSwap('main', null, $head))->toThrow(RefConflict::class)
        ->and($arena->readRef()->head?->toString())->toBe($head->toString());

    expect($arena->compareAndSwapToEmpty('main', $head)->head)->toBeNull();
})->with('arenaBackends');
