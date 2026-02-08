<?php declare(strict_types=1);

use Cognesy\Messages\Message;
use Cognesy\Messages\MessageStore\Storage\JsonlStorage;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/instructor-jsonl-test-' . uniqid();
    mkdir($this->tempDir, 0755, true);
    $this->storage = new JsonlStorage($this->tempDir);
});

afterEach(function () {
    // Clean up temp files
    $files = glob($this->tempDir . '/*');
    foreach ($files as $file) {
        unlink($file);
    }
    rmdir($this->tempDir);
});

describe('session operations', function () {
    test('creates a session file', function () {
        $sessionId = $this->storage->createSession('test-session');

        expect($sessionId)->toBe('test-session');
        expect($this->storage->hasSession($sessionId))->toBeTrue();
        expect(file_exists($this->tempDir . '/test-session.jsonl'))->toBeTrue();
    });

    test('session file contains header', function () {
        $sessionId = $this->storage->createSession('test-session');

        $content = file_get_contents($this->tempDir . '/test-session.jsonl');
        $header = json_decode(explode("\n", $content)[0], true);

        expect($header['type'])->toBe('session');
        expect($header['id'])->toBe('test-session');
        expect($header)->toHaveKey('createdAt');
    });

    test('deletes session file', function () {
        $sessionId = $this->storage->createSession('to-delete');
        $file = $this->tempDir . '/to-delete.jsonl';

        expect(file_exists($file))->toBeTrue();

        $this->storage->delete($sessionId);

        expect(file_exists($file))->toBeFalse();
        expect($this->storage->hasSession($sessionId))->toBeFalse();
    });
});

describe('message persistence', function () {
    test('appends messages to JSONL file', function () {
        $sessionId = $this->storage->createSession();

        $this->storage->append($sessionId, 'messages', new Message('user', 'Hello'));
        $this->storage->append($sessionId, 'messages', new Message('assistant', 'Hi there'));

        // Verify file contents
        $lines = file($this->tempDir . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $sessionId) . '.jsonl');
        expect(count($lines))->toBe(3); // 1 header + 2 messages
    });

    test('persisted messages can be reloaded', function () {
        $sessionId = $this->storage->createSession('reload-test');

        $msg1 = $this->storage->append($sessionId, 'messages', new Message('user', 'First'));
        $msg2 = $this->storage->append($sessionId, 'messages', new Message('assistant', 'Second'));

        // Create new storage instance to force reload from file
        $newStorage = new JsonlStorage($this->tempDir);
        $store = $newStorage->load($sessionId);

        $messages = $store->section('messages')->messages();
        expect($messages->count())->toBe(2);
        expect($messages->first()->toString())->toBe('First');
        expect($messages->last()->toString())->toBe('Second');
    });

    test('maintains parentId chain across reload', function () {
        $sessionId = $this->storage->createSession('parent-test');

        $msg1 = $this->storage->append($sessionId, 'messages', new Message('user', 'First'));
        $msg2 = $this->storage->append($sessionId, 'messages', new Message('assistant', 'Second'));

        // Reload
        $newStorage = new JsonlStorage($this->tempDir);
        $reloaded = $newStorage->get($sessionId, $msg2->id);

        expect($reloaded->parentId())->toBe($msg1->id);
    });
});

describe('branching with JSONL', function () {
    test('navigateTo changes leaf for new appends', function () {
        $sessionId = $this->storage->createSession();

        $msg1 = $this->storage->append($sessionId, 'messages', new Message('user', 'First'));
        $msg2 = $this->storage->append($sessionId, 'messages', new Message('assistant', 'Second'));

        $this->storage->navigateTo($sessionId, $msg1->id);
        $msg3 = $this->storage->append($sessionId, 'messages', new Message('user', 'Branch'));

        expect($msg3->parentId())->toBe($msg1->id);
    });

    test('getPath follows parentId chain', function () {
        $sessionId = $this->storage->createSession();

        $msg1 = $this->storage->append($sessionId, 'messages', new Message('user', 'First'));
        $msg2 = $this->storage->append($sessionId, 'messages', new Message('assistant', 'Second'));

        // Create a branch
        $this->storage->navigateTo($sessionId, $msg1->id);
        $msg3 = $this->storage->append($sessionId, 'messages', new Message('user', 'Branch'));

        // Path to branch should not include msg2
        $path = $this->storage->getPath($sessionId, $msg3->id);

        expect($path->count())->toBe(2);
        expect($path->first()->id)->toBe($msg1->id);
        expect($path->last()->id)->toBe($msg3->id);
    });

    test('fork creates new session file', function () {
        $sessionId = $this->storage->createSession('original');

        $msg1 = $this->storage->append($sessionId, 'messages', new Message('user', 'First'));
        $msg2 = $this->storage->append($sessionId, 'messages', new Message('assistant', 'Second'));
        $msg3 = $this->storage->append($sessionId, 'messages', new Message('user', 'Third'));

        $forkedId = $this->storage->fork($sessionId, $msg2->id);

        expect($this->storage->hasSession($forkedId))->toBeTrue();

        $forkedPath = $this->storage->getPath($forkedId);
        expect($forkedPath->count())->toBe(2);
    });
});

describe('labels in JSONL', function () {
    test('labels are persisted to file', function () {
        $sessionId = $this->storage->createSession('label-test');
        $msg = $this->storage->append($sessionId, 'messages', new Message('user', 'Important'));

        $this->storage->addLabel($sessionId, $msg->id, 'checkpoint');

        // Reload and check
        $newStorage = new JsonlStorage($this->tempDir);
        $labels = $newStorage->getLabels($sessionId);

        expect($labels)->toHaveKey($msg->id);
        expect($labels[$msg->id])->toBe('checkpoint');
    });
});

describe('JSONL file format', function () {
    test('each entry is valid JSON on its own line', function () {
        $sessionId = $this->storage->createSession('format-test');

        $this->storage->append($sessionId, 'messages', new Message('user', 'Hello'));
        $this->storage->append($sessionId, 'messages', new Message('assistant', 'Hi'));

        $file = $this->tempDir . '/format-test.jsonl';
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $decoded = json_decode($line, true);
            expect($decoded)->not->toBeNull();
            expect($decoded)->toHaveKey('type');
        }
    });

    test('message entries have required fields', function () {
        $sessionId = $this->storage->createSession('fields-test');

        $this->storage->append($sessionId, 'messages', new Message('user', 'Test'));

        $file = $this->tempDir . '/fields-test.jsonl';
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        // Second line is the message
        $entry = json_decode($lines[1], true);

        expect($entry['type'])->toBe('message');
        expect($entry)->toHaveKey('id');
        expect($entry)->toHaveKey('parentId');
        expect($entry)->toHaveKey('section');
        expect($entry)->toHaveKey('timestamp');
        expect($entry)->toHaveKey('data');
    });
});
