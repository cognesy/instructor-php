<?php declare(strict_types=1);

use Cognesy\Messages\Message;
use Cognesy\Messages\MessageStore\Storage\InMemoryStorage;

beforeEach(function () {
    $this->storage = new InMemoryStorage();
});

describe('session operations', function () {
    test('creates a session with auto-generated ID', function () {
        $sessionId = $this->storage->createSession();

        expect($sessionId)->toBeString();
        expect($this->storage->hasSession($sessionId))->toBeTrue();
    });

    test('creates a session with provided ID', function () {
        $sessionId = $this->storage->createSession('my-session');

        expect($sessionId)->toBe('my-session');
        expect($this->storage->hasSession('my-session'))->toBeTrue();
    });

    test('loads empty session as empty MessageStore', function () {
        $sessionId = $this->storage->createSession();
        $store = $this->storage->load($sessionId);

        expect($store->sections()->count())->toBe(0);
    });

    test('deletes a session', function () {
        $sessionId = $this->storage->createSession();
        expect($this->storage->hasSession($sessionId))->toBeTrue();

        $this->storage->delete($sessionId);
        expect($this->storage->hasSession($sessionId))->toBeFalse();
    });

    test('throws when loading non-existent session', function () {
        $this->storage->load('non-existent');
    })->throws(RuntimeException::class);
});

describe('message operations', function () {
    test('appends a message to a section', function () {
        $sessionId = $this->storage->createSession();
        $message = new Message('user', 'Hello');

        $stored = $this->storage->append($sessionId, 'messages', $message);

        expect($stored->id)->toBe($message->id);
        expect($this->storage->get($sessionId, $message->id))->not->toBeNull();
    });

    test('sets parentId on appended messages', function () {
        $sessionId = $this->storage->createSession();

        $msg1 = $this->storage->append($sessionId, 'messages', new Message('user', 'First'));
        $msg2 = $this->storage->append($sessionId, 'messages', new Message('assistant', 'Second'));

        expect($msg1->parentId())->toBeNull();
        expect($msg2->parentId())->toBe($msg1->id);
    });

    test('gets messages from a section', function () {
        $sessionId = $this->storage->createSession();
        $this->storage->append($sessionId, 'messages', new Message('user', 'Hello'));
        $this->storage->append($sessionId, 'messages', new Message('assistant', 'Hi there'));

        $messages = $this->storage->getSection($sessionId, 'messages');

        expect($messages->count())->toBe(2);
    });

    test('limits messages returned from section', function () {
        $sessionId = $this->storage->createSession();
        $this->storage->append($sessionId, 'messages', new Message('user', 'One'));
        $this->storage->append($sessionId, 'messages', new Message('assistant', 'Two'));
        $this->storage->append($sessionId, 'messages', new Message('user', 'Three'));

        $messages = $this->storage->getSection($sessionId, 'messages', 2);

        expect($messages->count())->toBe(2);
        expect($messages->last()->toString())->toBe('Three');
    });

    test('gets message by ID', function () {
        $sessionId = $this->storage->createSession();
        $original = new Message('user', 'Find me');
        $this->storage->append($sessionId, 'messages', $original);

        $found = $this->storage->get($sessionId, $original->id);

        expect($found)->not->toBeNull();
        expect($found->toString())->toBe('Find me');
    });

    test('returns null for non-existent message ID', function () {
        $sessionId = $this->storage->createSession();

        expect($this->storage->get($sessionId, 'non-existent'))->toBeNull();
    });
});

describe('branching operations', function () {
    test('tracks leaf message ID', function () {
        $sessionId = $this->storage->createSession();
        expect($this->storage->getLeafId($sessionId))->toBeNull();

        $msg = $this->storage->append($sessionId, 'messages', new Message('user', 'Hello'));
        expect($this->storage->getLeafId($sessionId))->toBe($msg->id);
    });

    test('navigates to a different message', function () {
        $sessionId = $this->storage->createSession();

        $msg1 = $this->storage->append($sessionId, 'messages', new Message('user', 'First'));
        $msg2 = $this->storage->append($sessionId, 'messages', new Message('assistant', 'Second'));

        expect($this->storage->getLeafId($sessionId))->toBe($msg2->id);

        $this->storage->navigateTo($sessionId, $msg1->id);
        expect($this->storage->getLeafId($sessionId))->toBe($msg1->id);
    });

    test('new messages attach to navigated position', function () {
        $sessionId = $this->storage->createSession();

        $msg1 = $this->storage->append($sessionId, 'messages', new Message('user', 'First'));
        $msg2 = $this->storage->append($sessionId, 'messages', new Message('assistant', 'Second'));

        // Navigate back to first message
        $this->storage->navigateTo($sessionId, $msg1->id);

        // New message should branch from first
        $msg3 = $this->storage->append($sessionId, 'messages', new Message('user', 'Branch'));

        expect($msg3->parentId())->toBe($msg1->id);
    });

    test('gets path from root to message', function () {
        $sessionId = $this->storage->createSession();

        $msg1 = $this->storage->append($sessionId, 'messages', new Message('user', 'First'));
        $msg2 = $this->storage->append($sessionId, 'messages', new Message('assistant', 'Second'));
        $msg3 = $this->storage->append($sessionId, 'messages', new Message('user', 'Third'));

        $path = $this->storage->getPath($sessionId, $msg3->id);

        expect($path->count())->toBe(3);
        expect($path->first()->id)->toBe($msg1->id);
        expect($path->last()->id)->toBe($msg3->id);
    });

    test('gets path to current leaf by default', function () {
        $sessionId = $this->storage->createSession();

        $this->storage->append($sessionId, 'messages', new Message('user', 'First'));
        $this->storage->append($sessionId, 'messages', new Message('assistant', 'Second'));

        $path = $this->storage->getPath($sessionId);

        expect($path->count())->toBe(2);
    });

    test('forks session from a message', function () {
        $sessionId = $this->storage->createSession();

        $msg1 = $this->storage->append($sessionId, 'messages', new Message('user', 'First'));
        $msg2 = $this->storage->append($sessionId, 'messages', new Message('assistant', 'Second'));
        $msg3 = $this->storage->append($sessionId, 'messages', new Message('user', 'Third'));

        // Fork from second message
        $forkedId = $this->storage->fork($sessionId, $msg2->id);

        expect($this->storage->hasSession($forkedId))->toBeTrue();

        $forkedPath = $this->storage->getPath($forkedId);
        expect($forkedPath->count())->toBe(2); // Only msg1 and msg2
    });
});

describe('label operations', function () {
    test('adds a label to a message', function () {
        $sessionId = $this->storage->createSession();
        $msg = $this->storage->append($sessionId, 'messages', new Message('user', 'Important'));

        $this->storage->addLabel($sessionId, $msg->id, 'checkpoint-1');

        $labels = $this->storage->getLabels($sessionId);
        expect($labels)->toHaveKey($msg->id);
        expect($labels[$msg->id])->toBe('checkpoint-1');
    });

    test('throws when labeling non-existent message', function () {
        $sessionId = $this->storage->createSession();
        $this->storage->addLabel($sessionId, 'non-existent', 'label');
    })->throws(RuntimeException::class);
});

describe('save and load roundtrip', function () {
    test('saves and loads MessageStore', function () {
        $sessionId = $this->storage->createSession();

        $this->storage->append($sessionId, 'messages', new Message('user', 'Hello'));
        $this->storage->append($sessionId, 'messages', new Message('assistant', 'Hi'));
        $this->storage->append($sessionId, 'buffer', new Message('system', 'Buffered'));

        $store = $this->storage->load($sessionId);

        // Verify sections
        $messages = $store->section('messages')->messages();
        expect($messages->count())->toBe(2);

        $buffer = $store->section('buffer')->messages();
        expect($buffer->count())->toBe(1);
    });

    test('save returns StoreMessagesResult with stats', function () {
        $sessionId = $this->storage->createSession();

        $this->storage->append($sessionId, 'messages', new Message('user', 'Hello'));
        $store = $this->storage->load($sessionId);

        // Add more messages
        $store = $store->section('messages')->appendMessages(new Message('assistant', 'Hi'));
        $store = $store->section('buffer')->appendMessages(new Message('system', 'Buffered'));

        $result = $store->toStorage($this->storage, $sessionId);

        expect($result->isSuccess())->toBeTrue();
        expect($result->sessionId)->toBe($sessionId);
        expect($result->sectionsStored)->toBe(2);
        expect($result->messagesStored)->toBe(3);
        expect($result->newMessages)->toBe(2); // assistant + buffered
        expect($result->durationMs())->toBeGreaterThanOrEqual(0);
        expect($result->errorMessage)->toBeNull();
    });
});
