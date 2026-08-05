<?php declare(strict_types=1);

use Cognesy\Messages\Message;
use Cognesy\Messages\MessageSessionId;
use Cognesy\Messages\MessageStore\Storage\JsonlStorage;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/instructor-jsonl-write-test-' . uniqid();
    mkdir($this->tempDir, 0755, true);
    $this->storage = new JsonlStorage($this->tempDir);
});

afterEach(function () {
    @chmod($this->tempDir, 0755);
    foreach (glob($this->tempDir . '/*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($this->tempDir);
});

describe('malformed session lines (regression: instructor-r50t.12)', function () {
    test('skips a corrupt line in the middle of a session file without warnings', function () {
        $sessionId = $this->storage->createSession();
        $this->storage->append($sessionId, 'messages', new Message('user', 'first'));
        $this->storage->append($sessionId, 'messages', new Message('assistant', 'second'));

        $file = glob($this->tempDir . '/*.jsonl')[0];
        $lines = array_values(array_filter(explode("\n", (string) file_get_contents($file)), fn($l) => $l !== ''));
        array_splice($lines, 2, 0, [
            '{"foo":"bar"}',        // decodes to an array with no type
            '"just a string"',      // decodes to a scalar
            '{"type":42}',          // type present but not a string
            '{"type":"message"}',   // message record with no id
            '{"type":"nextgen"}',   // unknown record type
        ]);
        file_put_contents($file, implode("\n", $lines) . "\n");

        // A warning here is promoted to an exception by the suite's error handler, so the
        // assertion is "loads cleanly", not merely "returns the right thing".
        $reloaded = new JsonlStorage($this->tempDir);
        $store = $reloaded->load($sessionId);

        expect($store->section('messages')->messages()->count())->toBe(2);
        expect($reloaded->getLeafId($sessionId))->not->toBeNull();
    });

    test('a trailing truncated line does not break loading', function () {
        $sessionId = $this->storage->createSession();
        $this->storage->append($sessionId, 'messages', new Message('user', 'kept'));

        $file = glob($this->tempDir . '/*.jsonl')[0];
        file_put_contents($file, '{"type":"mess', FILE_APPEND);

        $store = (new JsonlStorage($this->tempDir))->load($sessionId);

        expect($store->section('messages')->messages()->count())->toBe(1);
    });
});

describe('save() write semantics (regression: instructor-r50t.16)', function () {
    test('a load -> mutate -> save cycle keeps every message in the session', function () {
        $sessionId = $this->storage->createSession();
        $this->storage->append($sessionId, 'messages', new Message('user', 'one'));
        $this->storage->append($sessionId, 'messages', new Message('assistant', 'two'));

        $store = $this->storage->load($sessionId);
        $result = $this->storage->save($sessionId, $store);

        expect($result->isSuccess())->toBeTrue();
        expect((new JsonlStorage($this->tempDir))->load($sessionId)->section('messages')->messages()->count())->toBe(2);
    });

    test('a fork keeps its messages when the parent session is saved', function () {
        $sessionId = $this->storage->createSession();
        $first = $this->storage->append($sessionId, 'messages', new Message('user', 'shared'));
        $this->storage->append($sessionId, 'messages', new Message('assistant', 'parent branch'));

        $forkId = $this->storage->fork($sessionId, $first->id());

        $this->storage->save($sessionId, $this->storage->load($sessionId));

        $forked = (new JsonlStorage($this->tempDir))->load($forkId);
        expect($forked->section('messages')->messages()->count())->toBe(1);
        expect($forked->section('messages')->messages()->first()->content()->toString())->toBe('shared');
    });

    test('save writes the file in one atomic move and leaves no temp files behind', function () {
        $sessionId = $this->storage->createSession();
        $this->storage->append($sessionId, 'messages', new Message('user', 'one'));

        $this->storage->save($sessionId, $this->storage->load($sessionId));

        $leftovers = array_filter(
            glob($this->tempDir . '/*') ?: [],
            fn(string $path) => !str_ends_with($path, '.jsonl'),
        );
        expect($leftovers)->toBe([]);
    });

    test('a failed save leaves the previous file contents intact', function () {
        $sessionId = $this->storage->createSession();
        $this->storage->append($sessionId, 'messages', new Message('user', 'must survive'));

        $file = glob($this->tempDir . '/*.jsonl')[0];
        $before = (string) file_get_contents($file);

        // Read-only directory: the temp file cannot be created, so the write fails before
        // the existing file is touched. The old code truncated first and lost it.
        chmod($this->tempDir, 0555);
        $result = $this->storage->save($sessionId, $this->storage->load($sessionId));
        chmod($this->tempDir, 0755);

        expect($result->isSuccess())->toBeFalse();
        expect((string) file_get_contents($file))->toBe($before);
        expect((new JsonlStorage($this->tempDir))->load($sessionId)->section('messages')->messages()->count())->toBe(1);
    })->skip(
        fn() => posix_geteuid() === 0,
        'root ignores directory permissions',
    );

    test('save keeps the session file readable rather than tightening it to 0600', function () {
        $sessionId = $this->storage->createSession();
        $this->storage->append($sessionId, 'messages', new Message('user', 'one'));

        $file = glob($this->tempDir . '/*.jsonl')[0];
        $before = fileperms($file) & 0777;

        $this->storage->save($sessionId, $this->storage->load($sessionId));
        clearstatcache(true, $file);

        expect(fileperms($file) & 0777)->toBe($before);
    });
});
