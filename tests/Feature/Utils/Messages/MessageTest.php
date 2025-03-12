<?php

namespace Tests\Feature\Utils\Messages;

use Cognesy\Utils\Messages\Message;
use Cognesy\Utils\Messages\Enums\MessageRole;
use Cognesy\Addons\Image\Image;
use Cognesy\Utils\Messages\Contracts\CanProvideMessage;
use Exception;
use RuntimeException;

/**
 * Test suite for the Message class and its traits
 */

// Basic Message Creation Tests
test('creates a message with default values', function () {
    $message = new Message();

    expect($message->role())->toBe(MessageRole::User)
        ->and($message->content())->toBe('')
        ->and($message->name())->toBe('')
        ->and($message->meta())->toBe([])
        ->and($message->id())->toBeString()->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
});

test('creates a message with specified values', function () {
    $message = new Message(
        role: 'assistant',
        content: 'Hello, world!',
        name: 'Claude',
        metadata: ['source' => 'test']
    );

    expect($message->role())->toBe(MessageRole::Assistant)
        ->and($message->content())->toBe('Hello, world!')
        ->and($message->name())->toBe('Claude')
        ->and($message->meta())->toBe(['source' => 'test']);
});

test('creates a message with MessageRole enum', function () {
    $message = new Message(
        role: MessageRole::Tool,
        content: 'Tool message'
    );

    expect($message->role())->toBe(MessageRole::Tool)
        ->and($message->content())->toBe('Tool message');
});

// HandlesAccess Tests
test('checks if message is empty', function () {
    $emptyMessage = new Message(content: '');
    $nonEmptyMessage = new Message(content: 'Some content');
    $metadataMessage = new Message(content: '', metadata: ['key' => 'value']);

    expect($emptyMessage->isEmpty())->toBeTrue()
        ->and($nonEmptyMessage->isEmpty())->toBeFalse()
        ->and($metadataMessage->isEmpty())->toBeFalse();
});

test('checks if message is null', function () {
    $nullMessage = new Message(role: '', content: '');
    $nonNullMessage = new Message(role: 'user', content: '');

    expect($nullMessage->isNull())->toBeTrue()
        ->and($nonNullMessage->isNull())->toBeFalse();
})->skip('Currently empty role is always set to user');

test('checks if message is composite', function () {
    $simpleMessage = new Message(content: 'Simple text');
    $compositeMessage = new Message(content: [
        ['type' => 'text', 'text' => 'Part 1'],
        ['type' => 'text', 'text' => 'Part 2']
    ]);

    expect($simpleMessage->isComposite())->toBeFalse()
        ->and($compositeMessage->isComposite())->toBeTrue();
});

test('accesses metadata values', function () {
    $message = new Message(metadata: [
        'temperature' => 0.7,
        'source' => 'user input'
    ]);

    expect($message->hasMeta())->toBeTrue()
        ->and($message->hasMeta('temperature'))->toBeTrue()
        ->and($message->hasMeta('nonexistent'))->toBeFalse()
        ->and($message->meta())->toBe(['temperature' => 0.7, 'source' => 'user input'])
        ->and($message->meta('temperature'))->toBe(0.7)
        ->and($message->meta('nonexistent'))->toBeNull()
        ->and($message->metaKeys())->toBe(['temperature', 'source']);
});

test('manages metadata through fluent interface', function () {
    $message = new Message();

    $message->withMeta(['key1' => 'value1']);
    expect($message->meta())->toBe(['key1' => 'value1']);

    $message->withMetaValue('key2', 'value2');
    expect($message->meta())->toBe(['key1' => 'value1', 'key2' => 'value2']);
});

// HandlesCreation Tests
test('creates a message using static make method', function () {
    $message = Message::make('assistant', 'Hello');

    expect($message)->toBeInstanceOf(Message::class)
        ->and($message->role())->toBe(MessageRole::Assistant)
        ->and($message->content())->toBe('Hello');
});

test('creates a message from string with default role', function () {
    $message = Message::fromString('Hello');

    expect($message)->toBeInstanceOf(Message::class)
        ->and($message->role())->toBe(MessageRole::User)
        ->and($message->content())->toBe('Hello');
});

test('creates a message from string with specific role', function () {
    $message = Message::fromString('Hello', 'system');

    expect($message)->toBeInstanceOf(Message::class)
        ->and($message->role())->toBe(MessageRole::System)
        ->and($message->content())->toBe('Hello');
});

test('creates a message from array', function () {
    $message = Message::fromArray([
        'role' => 'assistant',
        'content' => 'Hello from array',
        '_metadata' => ['source' => 'test array']
    ]);

    expect($message)->toBeInstanceOf(Message::class)
        ->and($message->role())->toBe(MessageRole::Assistant)
        ->and($message->content())->toBe('Hello from array')
        ->and($message->meta())->toBe(['source' => 'test array']);
});

test('creates a message from content with role', function () {
    $message = Message::fromContent('system', 'System instruction');

    expect($message)->toBeInstanceOf(Message::class)
        ->and($message->role())->toBe(MessageRole::System)
        ->and($message->content())->toBe('System instruction');
});

test('creates a message from existing message through clone', function () {
    $original = new Message('user', 'Original message');
    $cloned = $original->clone();

    expect($cloned)->toBeInstanceOf(Message::class)
        ->and($cloned->role())->toBe(MessageRole::User)
        ->and($cloned->content())->toBe('Original message')
        ->and($cloned->id())->not->toBe($original->id());
});

test('creates a message from various source types', function () {
    $stringMessage = Message::fromAnyMessage('String message');
    $arrayMessage = Message::fromAnyMessage(['role' => 'assistant', 'content' => 'Array message']);
    $messageObject = new Message('system', 'Object message');
    $messageFromObject = Message::fromAnyMessage($messageObject);

    expect($stringMessage->content())->toBe('String message')
        ->and($arrayMessage->content())->toBe('Array message')
        ->and($messageFromObject->content())->toBe('Object message')
        ->and($messageFromObject->id())->not->toBe($messageObject->id());
});

//test('throws exception for invalid message type in fromAnyMessage', function () {
//    Message::fromAnyMessage(123);
//})->throws(Exception::class, 'Invalid message type');

// Mock CanProvideMessage for testing fromInput
class MockMessageProvider implements CanProvideMessage {
    public function toMessage(): Message {
        return new Message('assistant', 'Message from provider');
    }
}

test('creates a message from various input types', function () {
    $stringInput = Message::fromInput('String input');
    $arrayInput = Message::fromInput(['key' => 'value']);
    $messageObject = new Message('system', 'Existing message');
    $messageFromMessage = Message::fromInput($messageObject);
    $provider = new MockMessageProvider();
    $messageFromProvider = Message::fromInput($provider);

    expect($stringInput->content())->toBe('String input')
        ->and($arrayInput->content())->toBeString()->toContain('key')
        ->and($messageFromMessage)->toBe($messageObject)
        ->and($messageFromProvider->content())->toBe('Message from provider');
});

// Mock Image class for testing fromImage
class MockImage extends Image {
    public function toArray(): array {
        return ['type' => 'image', 'url' => 'http://example.com/image.jpg'];
    }
}

test('creates a message from an image', function () {
    $image = new MockImage('http://example.com/image.jpg', 'image/jpeg');
    $message = Message::fromImage($image, 'user');

    expect($message)->toBeInstanceOf(Message::class)
        ->and($message->role())->toBe(MessageRole::User)
        ->and($message->content())->toBe(['type' => 'image', 'url' => 'http://example.com/image.jpg']);
});

// HandlesMutation Tests
test('adds content part to a message', function () {
    $message = new Message('user', []);
    $message->addContentPart('Part 1');

    expect($message->content())->toBe([
        'type' => 'text',
        'content' => 'Part 1'
    ]);

    $message->addContentPart(['type' => 'image', 'url' => 'http://example.com/image.jpg']);
    expect($message->content())->toBe([
        'type' => 'text',
        'content' => 'Part 1',
        'type' => 'image',
        'url' => 'http://example.com/image.jpg'
    ]);
});

test('changes message role using withRole', function () {
    $message = new Message('user', 'Content');
    $message->withRole('assistant');

    expect($message->role())->toBe(MessageRole::Assistant);
});

// HandlesTransformation Tests
test('converts message to array', function () {
    $message = new Message(
        role: 'system',
        content: 'System instruction',
        name: 'System',
        metadata: ['source' => 'test']
    );

    $array = $message->toArray();

    expect($array)->toBe([
        'role' => 'system',
        'name' => 'System',
        'content' => 'System instruction',
        '_metadata' => ['source' => 'test']
    ]);
});

test('converts simple message to string', function () {
    $message = new Message('user', 'Simple text content');

    expect($message->toString())->toBe('Simple text content');
});

test('throws exception when converting non-text composite message to string', function () {
    $message = new Message('user', [
        ['type' => 'image', 'url' => 'http://example.com/image.jpg']
    ]);

    $message->toString();
})->throws(RuntimeException::class, 'Message contains non-text parts and cannot be flattened to text');

test('converts message to role-prefixed string', function () {
    $message = new Message('assistant', 'Hello, I am an assistant');

    expect($message->toRoleString())->toBe('assistant: Hello, I am an assistant');
});

test('converts to composite message', function () {
    $simpleMessage = new Message('user', 'Simple text');
    $compositeMessage = $simpleMessage->toCompositeMessage();

    expect($compositeMessage->isComposite())->toBeTrue()
        ->and($compositeMessage->content())->toBe([
            ['type' => 'text', 'text' => 'Simple text']
        ]);

    $alreadyComposite = new Message('user', [
        ['type' => 'text', 'text' => 'Already composite']
    ]);
    $stillComposite = $alreadyComposite->toCompositeMessage();

    expect($stillComposite->content())->toBe([
        ['type' => 'text', 'text' => 'Already composite']
    ]);
});

// Static Utility Tests
test('checks if message array becomes composite', function () {
    $simpleArray = ['content' => 'Simple text'];
    $compositeArray = ['content' => [['type' => 'text', 'text' => 'Part 1']]];

    expect(Message::becomesComposite($simpleArray))->toBeFalse()
        ->and(Message::becomesComposite($compositeArray))->toBeTrue();
});

test('checks if array has role and content', function () {
    $validArray = ['role' => 'user', 'content' => 'Content'];
    $validWithMetadata = ['role' => 'user', '_metadata' => ['key' => 'value']];
    $invalidArray = ['content' => 'Missing role'];

    expect(Message::hasRoleAndContent($validArray))->toBeTrue()
        ->and(Message::hasRoleAndContent($validWithMetadata))->toBeTrue()
        ->and(Message::hasRoleAndContent($invalidArray))->toBeFalse();
});