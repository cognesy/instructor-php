<?php declare(strict_types=1);

namespace Cognesy\Messages\Tests\Support;

use Cognesy\Messages\Message;
use Cognesy\Messages\MessageId;
use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\Contracts\CanStoreMessages;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Messages\MessageStore\Section;
use RuntimeException;

/**
 * The branching behaviour every storage backend must share, as one set of test bodies.
 *
 * The defects behind instructor-r50t.4 (getPath() looping forever on a parentId cycle) and
 * .7 (fork() accepting an unknown message id) were present in both backends with the same
 * shape, so the tests are written once and registered per backend. Registration is split
 * across two lanes on purpose: InMemoryStorage touches no filesystem and belongs in the
 * fast lane that CI runs, while JsonlStorage must live in the Integration lane - the
 * test-placement QA rule bars filesystem I/O from Unit/Feature/Regression.
 *
 * @see \Cognesy\Messages\Tests\Feature\MessageStore\InMemoryStorageBranchingTest
 * @see \Cognesy\Messages\Tests\Integration\MessageStore\JsonlStorageBranchingTest
 */
final class StorageBranchingContract
{
    /**
     * @param callable(): CanStoreMessages $makeStorage a fresh, empty backend per test
     */
    public static function register(string $backendName, callable $makeStorage): void {
        describe($backendName, function () use ($makeStorage) {
            test('getPath stops instead of looping forever on a parentId cycle (regression: instructor-r50t.4)', function () use ($makeStorage) {
                $storage = $makeStorage();
                $sessionId = $storage->createSession();

                // A cycle is not reachable through append(), which always attaches to the
                // current leaf. It IS reachable by storing hand-built messages, which is what
                // a corrupted or externally written session looks like once loaded.
                $idA = new MessageId('00000000-0000-4000-8000-00000000000a');
                $idB = new MessageId('00000000-0000-4000-8000-00000000000b');
                $storage->save($sessionId, MessageStore::fromSections(
                    new Section('messages', new Messages(
                        new Message(role: 'user', content: 'a', parentId: $idB, id: $idA),
                        new Message(role: 'assistant', content: 'b', parentId: $idA, id: $idB),
                    )),
                ));

                $path = $storage->getPath($sessionId, $idA);

                // Reaching this line at all is the assertion: before the fix the walk never
                // terminated. The bound then pins that it stops after one lap, not later.
                expect($path->count())->toBeLessThanOrEqual(2);
                expect($path->count())->toBeGreaterThan(0);
            });

            test('getPath walks a normal chain root-first (regression: instructor-r50t.4)', function () use ($makeStorage) {
                $storage = $makeStorage();
                $sessionId = $storage->createSession();
                $first = $storage->append($sessionId, 'messages', new Message('user', 'one'));
                $second = $storage->append($sessionId, 'messages', new Message('assistant', 'two'));

                $path = $storage->getPath($sessionId, $second->id());

                expect($path->count())->toBe(2);
                expect($path->first()->content()->toString())->toBe('one');
                expect($path->last()->content()->toString())->toBe('two');
                expect((string) $path->first()->id())->toBe((string) $first->id());
            });

            test('fork rejects an unknown message id (regression: instructor-r50t.7)', function () use ($makeStorage) {
                $storage = $makeStorage();
                $sessionId = $storage->createSession();
                $storage->append($sessionId, 'messages', new Message('user', 'one'));

                expect(fn() => $storage->fork($sessionId, new MessageId('00000000-0000-4000-8000-0000000000ff')))
                    ->toThrow(RuntimeException::class, 'Message not found');
            });

            test('a rejected fork leaves the source session untouched (regression: instructor-r50t.7)', function () use ($makeStorage) {
                $storage = $makeStorage();
                $sessionId = $storage->createSession();
                $storage->append($sessionId, 'messages', new Message('user', 'one'));

                try {
                    $storage->fork($sessionId, new MessageId('00000000-0000-4000-8000-0000000000fe'));
                } catch (RuntimeException) {
                    // expected
                }

                expect($storage->load($sessionId)->section('messages')->messages()->count())->toBe(1);
            });

            test('fork copies the path up to the fork point (regression: instructor-r50t.7)', function () use ($makeStorage) {
                $storage = $makeStorage();
                $sessionId = $storage->createSession();
                $first = $storage->append($sessionId, 'messages', new Message('user', 'shared'));
                $storage->append($sessionId, 'messages', new Message('assistant', 'not in fork'));

                $forkId = $storage->fork($sessionId, $first->id());

                $forked = $storage->load($forkId)->section('messages')->messages();
                expect($forked->count())->toBe(1);
                expect($forked->first()->content()->toString())->toBe('shared');
            });
        });
    }
}
