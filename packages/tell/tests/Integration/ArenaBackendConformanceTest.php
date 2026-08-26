<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/Pest.php';

use Cognesy\Tell\Canonical\CanonicalConversationRoot;
use Cognesy\Tell\Canonical\CanonicalHash;
use Cognesy\Tell\Canonical\CanonicalMessage;
use Cognesy\Tell\Canonical\CanonicalRole;
use Cognesy\Tell\Canonical\CanonicalTextPart;
use Cognesy\Tell\Workspace\ArenaRefConflict;
use Cognesy\Tell\Workspace\ArenaIntegrityException;
use Cognesy\Tell\Workspace\ArenaStore;
use Cognesy\Tell\Workspace\BranchName;
use Cognesy\Tell\Workspace\CanUseTellArena;
use Cognesy\Tell\Workspace\InMemoryArenaStore;

dataset('arenaBackends', [
    'filesystem' => [static function (): CanUseTellArena {
        $project = tellTestProject();
        $workspace = tellTestFactory()->workspace()->initialize($project)->workspace;
        return new ArenaStore($workspace);
    }],
    'memory' => [static fn (): CanUseTellArena => new InMemoryArenaStore],
]);

it('shares content addressing, CAS, branch, checkout, and failure atomicity', function (Closure $factory): void {
    $arena = $factory();
    $root = new CanonicalConversationRoot('conformance-root', [
        new CanonicalMessage(CanonicalRole::User, [new CanonicalTextPart('preserve this')]),
    ]);
    $head = $arena->put($root);

    expect($arena->exists($head))->toBeTrue()
        ->and($arena->get($head)->toCanonicalArray())->toBe($root->toCanonicalArray());

    $published = $arena->compareAndSwap('main', null, $head);
    $arena->createBranch(BranchName::from('review'), $published);
    $arena->checkout('review');

    expect($arena->readRef()->head?->toString())->toBe($head->toString())
        ->and($arena->readOptionalRef('branches/review')?->head?->toString())->toBe($head->toString())
        ->and($arena->readCurrentBranch()->branch)->toBe('review');

    $missing = new CanonicalHash(str_repeat('a', 64));
    expect(fn () => $arena->compareAndSwap('main', $head, $missing))->toThrow(ArenaIntegrityException::class)
        ->and($arena->readRef()->head?->toString())->toBe($head->toString())
        ->and(fn () => $arena->compareAndSwap('main', null, $head))->toThrow(ArenaRefConflict::class)
        ->and($arena->readRef()->head?->toString())->toBe($head->toString());

    expect($arena->compareAndSwapToEmpty('main', $head)->head)->toBeNull();
})->with('arenaBackends');
