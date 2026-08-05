<?php declare(strict_types=1);

use Cognesy\Messages\Message;
use Cognesy\Messages\MessageId;
use Cognesy\Messages\MessageStore\Storage\JsonlStorage;
use Cognesy\Messages\Tests\Support\StorageBranchingContract;

// Same contract as tests/Feature/MessageStore/InMemoryStorageBranchingTest.php. It sits in
// the Integration lane rather than next to it because it writes real files, which the
// test-placement QA rule bars from the fast lanes.
StorageBranchingContract::register('JsonlStorage', function () {
    $dir = sys_get_temp_dir() . '/instructor-storage-branching-' . uniqid();
    mkdir($dir, 0755, true);

    return new JsonlStorage($dir);
});

test('a rejected fork creates no session file (regression: instructor-r50t.7)', function () {
    $dir = sys_get_temp_dir() . '/instructor-storage-fork-files-' . uniqid();
    mkdir($dir, 0755, true);
    $storage = new JsonlStorage($dir);
    $sessionId = $storage->createSession();
    $storage->append($sessionId, 'messages', new Message('user', 'one'));

    $before = count(glob($dir . '/*.jsonl') ?: []);

    try {
        $storage->fork($sessionId, new MessageId('00000000-0000-4000-8000-0000000000fd'));
    } catch (RuntimeException) {
        // expected
    }

    // createSession() used to run before the fork point was validated, leaving an empty
    // session file behind for every rejected fork.
    expect(count(glob($dir . '/*.jsonl') ?: []))->toBe($before);

    foreach (glob($dir . '/*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($dir);
});
