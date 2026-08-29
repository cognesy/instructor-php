<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Pest.php';

use Cognesy\Tell\Workspace\Arena\CanUseArena;
use Cognesy\Tell\Workspace\Arena\Exception\ArenaIntegrityException;
use Cognesy\Tell\Workspace\Arena\Exception\RefConflict;
use Cognesy\Tell\Workspace\Arena\FilesystemArena;
use Cognesy\Tell\Workspace\Arena\InMemoryArena;
use Cognesy\Tell\Workspace\Arena\ObjectHash;
use Cognesy\Tell\Workspace\Arena\Record\ConversationRoot;
use Cognesy\Tell\Workspace\Arena\Record\Message as RecordMessage;
use Cognesy\Tell\Workspace\Arena\Record\Role;
use Cognesy\Tell\Workspace\Arena\Record\TextPart;

dataset('arenaBackends', [
    'filesystem' => [static function (): CanUseArena {
        $project = tellTestProject();
        $workspace = tellTestFactory()->workspace()->initialize($project)->workspace;

        return new FilesystemArena($workspace);
    }],
    'memory' => [static fn (): CanUseArena => new InMemoryArena()],
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
