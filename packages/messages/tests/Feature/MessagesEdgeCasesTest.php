<?php

use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Messages\Tests\Fixtures\MockMessageProvider;
use Cognesy\Messages\Tests\Fixtures\MockMessagesProvider;

// Test edge cases for empty messages
test('handles empty messages collection correctly', function () {
    $messages = Messages::empty();
    
    expect($messages->isEmpty())->toBeTrue()
        ->and($messages->messageList())->toBeInstanceOf(\Cognesy\Messages\MessageList::class)
        ->and($messages->messageList()->isEmpty())->toBeTrue()
        ->and($messages->toArray())->toBeEmpty();
    
    // Operations on empty collection
    $merged = $messages->toMergedPerRole();
    expect($merged)->toBeInstanceOf(Messages::class)
        ->and($merged->isEmpty())->toBeTrue();
    
    $filtered = $messages->forRoles(['user']);
    expect($filtered->isEmpty())->toBeTrue();
    
    $reversed = $messages->reversed();
    expect($reversed->isEmpty())->toBeTrue();
});

test('handles collections with empty messages', function () {
    $emptyMessage = new Message();
    $messages = Messages::empty()->appendMessage($emptyMessage);
    
    expect($messages->notEmpty())->toBeFalse() // Should consider collection empty despite having an empty message
        ->and($messages->isEmpty())->toBeTrue()
        ->and($messages->toArray())->toBeEmpty(); // Empty messages should be filtered out
});

// Test error handling
test('fromArray normalizes completely invalid structure to user text message', function () {
    $messages = Messages::fromArray([
        'not-a-message-object'
    ]);

    expect($messages)->toBeInstanceOf(Messages::class)
        ->and($messages->count())->toBe(1)
        ->and($messages->first()->role()->value)->toBe('user')
        ->and($messages->first()->content()->toString())->toBe('not-a-message-object');
});

test('fromAnyArray throws exception with invalid nested structure', function () {
    expect(fn() => Messages::fromAnyArray([
        ['invalid' => 'structure']
    ]))->toThrow(Exception::class);
});

test('fromAny handles various input types correctly', function () {
    // String
    $fromString = Messages::fromAny('Hello');
    expect($fromString)->toBeInstanceOf(Messages::class)
        ->and($fromString->first()->content()->toString())->toBe('Hello');
    
    // Array
    $fromArray = Messages::fromAny([
        ['role' => 'user', 'content' => 'Hello']
    ]);
    expect($fromArray)->toBeInstanceOf(Messages::class)
        ->and($fromArray->first()->content()->toString())->toBe('Hello');
    
    // Message
    $message = new Message('user', 'Hello');
    $fromMessage = Messages::fromAny($message);
    expect($fromMessage)->toBeInstanceOf(Messages::class)
        ->and($fromMessage->first())->toBe($message);
    
    // Messages
    $originalMessages = Messages::fromString('Hello');
    $fromMessages = Messages::fromAny($originalMessages);
    expect($fromMessages)->toBe($originalMessages); // Should return the same instance
    
    // Invalid
    //expect(fn() => Messages::fromAny(123))->toThrow(Exception::class);
});

// Test interface implementations
test('fromInput handles objects implementing CanProvideMessage', function () {
    $provider = new MockMessageProvider();
    $messages = Messages::fromInput($provider);
    
    expect($messages)->toBeInstanceOf(Messages::class)
        ->and($messages->first()->content()->toString())->toBe('From message provider');
});

test('fromInput handles objects implementing CanProvideMessages', function () {
    $provider = new MockMessagesProvider();
    $messages = Messages::fromInput($provider);
    
    expect($messages)->toBeInstanceOf(Messages::class)
        ->and($messages->first()->content()->toString())->toBe('From messages provider');
});

test('fromInput handles MessageList', function () {
    $list = \Cognesy\Messages\MessageList::fromArray([
        new Message('user', 'Hello'),
        new Message('assistant', 'Hi'),
    ]);

    $messages = Messages::fromInput($list);

    expect($messages)->toBeInstanceOf(Messages::class)
        ->and($messages->count())->toBe(2)
        ->and($messages->first()->content()->toString())->toBe('Hello');
});

test('asString handles empty arrays', function () {
    $result = Messages::asString([]);
    
    expect($result)->toBe('');
});

test('asString handles arrays with empty messages', function () {
    $result = Messages::asString([
        [],
        ['role' => 'user', 'content' => '']
    ]);
    
    expect($result)->toBe('');
});

test('asString throws exception for composite messages', function () {
    $messagesArray = [
        [
            'role' => 'user',
            'content' => [
                ['type' => 'text', 'text' => 'Composite content']
            ]
        ]
    ];
    
    expect(fn() => Messages::asString($messagesArray))->toThrow(RuntimeException::class);
});

test('asString works with custom renderer', function () {
    $messagesArray = [
        ['role' => 'user', 'content' => 'Hello'],
        ['role' => 'assistant', 'content' => 'Hi']
    ];

    $result = Messages::asString($messagesArray, ' | ', function(array $message): string {
        /** @var array{role: string, content: string} $message */
        return strtoupper($message['role']) . ': ' . $message['content'] . ' | ';
    });

    expect($result)->toBe('USER: Hello | ASSISTANT: Hi | ');
});

// Test edge cases in transformation methods
test('toMergedPerRole handles role changes with empty content', function () {
    $messages = Messages::fromArray([
        ['role' => 'user', 'content' => 'Hello'],
        ['role' => 'user', 'content' => ''], // Empty content
        ['role' => 'assistant', 'content' => 'Hi'],
        ['role' => 'assistant', 'content' => ''] // Empty content
    ]);
    
    $merged = $messages->toMergedPerRole();
    
    expect($merged->messageList()->count())->toBe(2)
        ->and($merged->first()->content()->toString())->toBe('Hello')
        ->and($merged->last()->content()->toString())->toBe('Hi');
});

test('toMergedPerRole handles collections with less than 3 messages correctly', function () {
    // Empty
    $empty = Messages::empty();
    $mergedEmpty = $empty->toMergedPerRole();
    expect($mergedEmpty->isEmpty())->toBeTrue();
    
    // Single message
    $single = Messages::fromString('Hello');
    $mergedSingle = $single->toMergedPerRole();
    expect($mergedSingle->messageList()->count())->toBe(1)
        ->and($mergedSingle->first()->content()->toString())->toBe('Hello');
    
    // Two messages, same role
    $twoSameRole = Messages::fromArray([
        ['role' => 'user', 'content' => 'Hello'],
        ['role' => 'user', 'content' => 'There']
    ]);
    $mergedTwoSame = $twoSameRole->toMergedPerRole();
    expect($mergedTwoSame->messageList()->count())->toBe(1)
        ->and($mergedTwoSame->first()->content()->toString())->toContain('Hello')
        ->and($mergedTwoSame->first()->content()->toString())->toContain('There');
    
    // Two messages, different roles
    $twoDiffRole = Messages::fromArray([
        ['role' => 'user', 'content' => 'Hello'],
        ['role' => 'assistant', 'content' => 'Hi']
    ]);
    $mergedTwoDiff = $twoDiffRole->toMergedPerRole();
    expect($mergedTwoDiff->messageList()->count())->toBe(2)
        ->and($mergedTwoDiff->first()->content()->toString())->toBe('Hello')
        ->and($mergedTwoDiff->last()->content()->toString())->toBe('Hi');
});

// Test map/reduce/filter edge cases
test('map on empty collection returns empty array', function () {
    $empty = Messages::empty();
    $result = $empty->map(fn($msg) => $msg->content()->toString());
    
    expect($result)->toBeArray()
        ->and($result)->toBeEmpty();
});

test('reduce on empty collection returns initial value', function () {
    $empty = Messages::empty();
    $result = $empty->reduce(fn($carry, $msg) => $carry + 1, 0);
    
    expect($result)->toBe(0);
});

test('filter removes empty messages', function () {
    $messages = Messages::empty();
    $messages = $messages->appendMessage(new Message('user', 'Hello'));
    $messages = $messages->appendMessage(new Message()); // Empty message
    
    $filtered = $messages->filter(fn($msg) => true); // Accept all, but empty should be filtered
    
    expect($filtered->messageList()->count())->toBe(1)
        ->and($filtered->first()->content()->toString())->toBe('Hello');
});

test('filter without callback keeps non-empty messages', function () {
    $messages = Messages::empty();
    $messages = $messages->appendMessage(new Message('user', 'Hello'));
    $messages = $messages->appendMessage(new Message('assistant', 'Hi'));
    $messages = $messages->appendMessage(new Message()); // Empty message

    $filtered = $messages->filter();

    expect($filtered->messageList()->count())->toBe(2)
        ->and($filtered->first()->content()->toString())->toBe('Hello')
        ->and($filtered->last()->content()->toString())->toBe('Hi');
});

// Test static helper methods
test('becomesEmpty correctly identifies empty inputs', function () {
    expect(Messages::becomesEmpty([]))->toBeTrue();
    expect(Messages::becomesEmpty(new Message()))->toBeTrue();
    expect(Messages::becomesEmpty(Messages::empty()))->toBeTrue();

    $list = \Cognesy\Messages\MessageList::empty();
    expect(Messages::becomesEmpty($list))->toBeTrue();
    
    expect(Messages::becomesEmpty(['not empty']))->toBeFalse();
    expect(Messages::becomesEmpty(new Message('user', 'content')))->toBeFalse();
    expect(Messages::becomesEmpty(Messages::fromString('content')))->toBeFalse();
});

test('becomesComposite identifies composite messages', function () {
    $regularArray = [
        'role' => 'user',
        'content' => 'Regular content'
    ];
    
    $compositeArray = [
        'role' => 'user',
        'content' => [
            ['type' => 'text', 'text' => 'Composite content'],
            ['type' => 'image_url', 'url' => 'http://example.com/image.png']
        ]
    ];
    
    expect(Messages::becomesComposite([$regularArray]))->toBeFalse();
    expect(Messages::becomesComposite([$compositeArray]))->toBeTrue();
    expect(Messages::becomesComposite([]))->toBeFalse();
});
