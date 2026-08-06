<?php declare(strict_types=1);

use Cognesy\Messages\Message;
use Cognesy\Messages\MessageId;
use Cognesy\Messages\MessageSessionId;
use Cognesy\Messages\MessageStore\Storage\JsonlStorage;

/**
 * Writes a session file by hand so a record can be shaped the current writer would never
 * produce - a role an older version allowed, an id that is not a UUID, a record with no
 * payload. That is the only way to reach the deserialization paths under test.
 */
function writeRawSession(string $dir, array $records, ?string $headerLeafId = null): MessageSessionId {
    $sessionId = MessageSessionId::generate();
    $lines = [json_encode([
        'type' => 'session',
        'version' => 1,
        'id' => $sessionId->toString(),
        'createdAt' => (new DateTimeImmutable())->format(DateTimeImmutable::ATOM),
        'leafId' => $headerLeafId,
    ])];
    foreach ($records as $record) {
        $lines[] = json_encode($record);
    }
    file_put_contents($dir . '/' . $sessionId->toString() . '.jsonl', implode("\n", $lines) . "\n");

    return $sessionId;
}

function messageRecord(string $id, ?string $parentId, string $role, string $content, string $section = 'messages'): array {
    return [
        'type' => 'message',
        'id' => $id,
        'parentId' => $parentId,
        'section' => $section,
        'timestamp' => (new DateTimeImmutable())->format(DateTimeImmutable::ATOM),
        'data' => ['id' => $id, 'role' => $role, 'content' => $content],
    ];
}

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

describe('unhydratable records are quarantined, not fatal', function () {
    // Message and MessageId validate on construction, which is right for new data and wrong
    // for data already on disk: one record an older version wrote made load() throw and took
    // every other message in the file with it.
    test('a record with a role this version rejects does not deny access to the session', function () {
        $goodId = (string) MessageId::generate();
        $badId = (string) MessageId::generate();
        $sessionId = writeRawSession($this->tempDir, [
            messageRecord($goodId, null, 'user', 'good'),
            messageRecord($badId, $goodId, 'bot', 'written by an older version'),
        ]);

        $storage = new JsonlStorage($this->tempDir);
        $store = $storage->load($sessionId);

        expect($store->section('messages')->messages()->count())->toBe(1);
        expect($store->toMessages()->first()->content()->toString())->toBe('good');
        expect($storage->quarantined($sessionId))->toBe([$badId => 'Invalid message role: bot']);
    });

    test('getSection skips the record and returns the rest', function () {
        $goodId = (string) MessageId::generate();
        $sessionId = writeRawSession($this->tempDir, [
            messageRecord($goodId, null, 'user', 'good'),
            messageRecord((string) MessageId::generate(), $goodId, 'bot', 'legacy'),
        ]);

        expect((new JsonlStorage($this->tempDir))->getSection($sessionId, 'messages')->count())->toBe(1);
    });

    test('get returns null for a quarantined id and the message for a sound one', function () {
        $goodId = (string) MessageId::generate();
        $badId = (string) MessageId::generate();
        $sessionId = writeRawSession($this->tempDir, [
            messageRecord($goodId, null, 'user', 'good'),
            messageRecord($badId, $goodId, 'bot', 'legacy'),
        ]);

        $storage = new JsonlStorage($this->tempDir);

        expect($storage->get($sessionId, new MessageId($goodId))?->content()->toString())->toBe('good');
        expect($storage->get($sessionId, new MessageId($badId)))->toBeNull();
        expect($storage->quarantined($sessionId))->toHaveKey($badId);
    });

    // The walk continues past the broken link, so a corrupt message mid-branch costs that
    // one message rather than every ancestor above it.
    test('getPath drops the corrupt link but keeps its ancestors', function () {
        $rootId = (string) MessageId::generate();
        $badId = (string) MessageId::generate();
        $tailId = (string) MessageId::generate();
        $sessionId = writeRawSession($this->tempDir, [
            messageRecord($rootId, null, 'user', 'root'),
            messageRecord($badId, $rootId, 'bot', 'legacy'),
            messageRecord($tailId, $badId, 'assistant', 'tail'),
        ]);

        $path = (new JsonlStorage($this->tempDir))->getPath($sessionId);

        expect($path->map(fn(Message $m) => $m->content()->toString()))->toBe(['root', 'tail']);
    });

    // MessageId validates during indexing, before any message is hydrated, so this used to
    // fail the session on open rather than on read.
    test('an id that is not a UUID is quarantined and the session still opens', function () {
        $goodId = (string) MessageId::generate();
        $sessionId = writeRawSession($this->tempDir, [
            messageRecord('not-a-uuid', null, 'user', 'weird'),
            messageRecord($goodId, null, 'user', 'good'),
        ]);

        $storage = new JsonlStorage($this->tempDir);

        expect($storage->load($sessionId)->toMessages()->count())->toBe(1);
        expect($storage->getLeafId($sessionId)?->toString())->toBe($goodId);
        expect($storage->quarantined($sessionId))->toHaveKey('not-a-uuid');
    });

    test('a header naming an unparseable leaf falls back to the last message', function () {
        $goodId = (string) MessageId::generate();
        $sessionId = writeRawSession($this->tempDir, [
            messageRecord($goodId, null, 'user', 'good'),
        ], headerLeafId: 'garbage');

        expect((new JsonlStorage($this->tempDir))->getLeafId($sessionId)?->toString())->toBe($goodId);
    });

    test('a message record with no data payload is quarantined', function () {
        $goodId = (string) MessageId::generate();
        $badId = (string) MessageId::generate();
        $record = messageRecord($badId, $goodId, 'user', 'x');
        unset($record['data']);
        $sessionId = writeRawSession($this->tempDir, [
            messageRecord($goodId, null, 'user', 'good'),
            $record,
        ]);

        $storage = new JsonlStorage($this->tempDir);

        expect($storage->load($sessionId)->toMessages()->count())->toBe(1);
        expect($storage->quarantined($sessionId))->toBe([$badId => 'Message record has no data payload']);
    });

    test('a sound session reports an empty quarantine', function () {
        $sessionId = $this->storage->createSession();
        $this->storage->append($sessionId, 'messages', new Message('user', 'one'));
        $this->storage->append($sessionId, 'messages', new Message('assistant', 'two'));

        expect($this->storage->quarantined($sessionId))->toBe([]);
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
