<?php

use Cognesy\Utils\Messages\Message;
use Cognesy\Utils\Messages\Messages;

// HandlesCreation static methods
test('static fromString creates Messages with correct role and content', function () {
    $messages = Messages::fromString('Test content', 'system');
    
    expect($messages)->toBeInstanceOf(Messages::class)
        ->and($messages->all())->toHaveCount(1)
        ->and($messages->first()->role()->value)->toBe('system')
        ->and($messages->first()->content()->toString())->toBe('Test content');
    
    // With default role
    $defaultRole = Messages::fromString('Default role test');
    expect($defaultRole->first()->role()->value)->toBe('user');
});

test('static fromArray handles various input formats', function () {
    // Array of message arrays
    $messages1 = Messages::fromArray([
        ['role' => 'user', 'content' => 'Message 1'],
        ['role' => 'assistant', 'content' => 'Message 2']
    ]);
    
    expect($messages1)->toBeInstanceOf(Messages::class)
        ->and($messages1->all())->toHaveCount(2);
    
    // Array of strings (should throw exception - needs role/content keys)
//    expect(fn() => Messages::fromArray(['string1', 'string2']))->toThrow(Exception::class);
    
    // Array with malformed entries
//    expect(fn() => Messages::fromArray([
//        ['role' => 'user', 'content' => 'Valid'],
//        ['invalid' => 'format']
//    ]))->toThrow(Exception::class);
});

test('static fromMessages handles various input combinations', function () {
    $message1 = new Message('user', 'Message 1');
    $message2 = new Message('assistant', 'Message 2');
    $messagesObj = Messages::fromArray([
        ['role' => 'system', 'content' => 'System message']
    ]);
    $messageArray = ['role' => 'user', 'content' => 'Array message'];
    
    // Single message
    $single = Messages::fromMessages([$message1]);
    expect($single->all())->toHaveCount(1)
        ->and($single->first()->content()->toString())->toBe('Message 1');
    
    // Multiple separate arguments
    $multiple = Messages::fromMessages([$message1, $message2]);
    expect($multiple->all())->toHaveCount(2);
    
    // Array of messages
    $array = Messages::fromMessages([$message1, $message2]);
    expect($array->all())->toHaveCount(2);
    
    // Invalid type
    //expect(fn() => Messages::fromMessages($message1, 'invalid'))->toThrow(InvalidArgumentException::class);
});

test('static fromAnyArray handles mixed array formats', function () {
    // Simple message array with role and content
    $simple = Messages::fromAnyArray(['role' => 'user', 'content' => 'Simple message']);
    expect($simple->all())->toHaveCount(1)
        ->and($simple->first()->content()->toString())->toBe('Simple message');
    
    // Array of different message formats
    $mixed = Messages::fromAnyArray([
        ['role' => 'user', 'content' => 'Array message'],
        'String message',
        new Message('assistant', 'Message object')
    ]);
    
    expect($mixed->all())->toHaveCount(3)
        ->and($mixed->first()->content()->toString())->toBe('Array message')
        ->and($mixed->all()[1]->content()->toString())->toBe('String message')
        ->and($mixed->all()[2]->content()->toString())->toBe('Message object');
    
    // Array with invalid entries
    expect(fn() => Messages::fromAnyArray([
        ['role' => 'user', 'content' => 'Valid'],
        ['invalid' => 'format']
    ]))->toThrow(Exception::class);
    
    expect(fn() => Messages::fromAnyArray([
        new Message('user', 'Valid'),
        123 // Invalid type
    ]))->toThrow(Exception::class);
});

test('static fromAny handles all supported input types', function () {
    // String
    $fromString = Messages::fromAny('String input');
    expect($fromString->all())->toHaveCount(1)
        ->and($fromString->first()->role()->value)->toBe('user')
        ->and($fromString->first()->content()->toString())->toBe('String input');
    
    // Message array
    $fromArray = Messages::fromAny(['role' => 'system', 'content' => 'Array input']);
    expect($fromArray->all())->toHaveCount(1)
        ->and($fromArray->first()->role()->value)->toBe('system')
        ->and($fromArray->first()->content()->toString())->toBe('Array input');
    
    // Array of messages
    $fromMultiArray = Messages::fromAny([
        ['role' => 'user', 'content' => 'First'],
        ['role' => 'assistant', 'content' => 'Second']
    ]);
    expect($fromMultiArray->all())->toHaveCount(2);
    
    // Message object
    $message = new Message('user', 'Message object');
    $fromMessage = Messages::fromAny($message);
    expect($fromMessage->all())->toHaveCount(1)
        ->and($fromMessage->first())->toBe($message);
    
    // Messages object
    $messages = Messages::fromString('Original messages');
    $fromMessages = Messages::fromAny($messages);
    expect($fromMessages)->toBe($messages); // Should return the same instance
    
    // Invalid input
    //expect(fn() => Messages::fromAny(123))->toThrow(Exception::class);
});

// HandlesConversion static methods
test('static asPerRoleArray merges messages with same role', function () {
    $messagesArray = [
        ['role' => 'user', 'content' => 'Message 1'],
        ['role' => 'user', 'content' => 'Message 2'],
        ['role' => 'assistant', 'content' => 'Response 1'],
        ['role' => 'assistant', 'content' => 'Response 2'],
        ['role' => 'user', 'content' => 'Message 3']
    ];
    
    $merged = Messages::asPerRoleArray($messagesArray);
    
    expect($merged)->toBeArray()
        ->and($merged)->toHaveCount(3)
        ->and($merged[0]['role'])->toBe('user')
        ->and($merged[0]['content'])->toContain('Message 1')
        ->and($merged[0]['content'])->toContain('Message 2')
        ->and($merged[1]['role'])->toBe('assistant')
        ->and($merged[1]['content'])->toContain('Response 1')
        ->and($merged[1]['content'])->toContain('Response 2')
        ->and($merged[2]['role'])->toBe('user')
        ->and($merged[2]['content'])->toBe('Message 3');
});

test('static asString concatenates message content with separator', function () {
    $messagesArray = [
        ['role' => 'user', 'content' => 'Hello'],
        ['role' => 'assistant', 'content' => 'Hi'],
        ['role' => 'user', 'content' => 'How are you?']
    ];
    
    // Default separator
    $defaultSeparator = Messages::asString($messagesArray);
    expect($defaultSeparator)->toBe("Hello\nHi\nHow are you?\n");
    
    // Custom separator
    $customSeparator = Messages::asString($messagesArray, ' | ');
    expect($customSeparator)->toBe("Hello | Hi | How are you? | ");
    
    // Custom renderer
    $customRenderer = Messages::asString($messagesArray, ' ', function($message) {
        return "[{$message['role']}] {$message['content']} ";
    });
    expect($customRenderer)->toBe("[user] Hello [assistant] Hi [user] How are you? ");
});

// Test convenience static checking methods
test('static becomesEmpty correctly identifies empty values', function () {
    // Empty array
    expect(Messages::becomesEmpty([]))->toBeTrue();
    
    // Empty Message object
    expect(Messages::becomesEmpty(new Message()))->toBeTrue();
    
    // Empty Messages collection
    expect(Messages::becomesEmpty(new Messages()))->toBeTrue();
    
    // Non-empty array
    expect(Messages::becomesEmpty(['not empty']))->toBeFalse();
    
    // Non-empty Message object
    expect(Messages::becomesEmpty(new Message('user', 'content')))->toBeFalse();
    
    // Non-empty Messages collection
    expect(Messages::becomesEmpty(Messages::fromString('content')))->toBeFalse();
});

test('static becomesComposite detects composite message structures', function () {
    // Empty array
    expect(Messages::becomesComposite([]))->toBeFalse();
    
    // Array with regular message
    $regularArray = [
        ['role' => 'user', 'content' => 'Regular message']
    ];
    expect(Messages::becomesComposite($regularArray))->toBeFalse();
    
    // Array with composite message
    $compositeArray = [
        ['role' => 'user', 'content' => [['type' => 'text', 'text' => 'Composite'], ['type' => 'text', 'text' => 'Composite 2']]]
    ];
    expect(Messages::becomesComposite($compositeArray))->toBeTrue();
    
    // Mixed array
    $mixedArray = [
        ['role' => 'user', 'content' => 'Regular message'],
        ['role' => 'assistant', 'content' => [['type' => 'text', 'text' => 'Composite'], ['type' => 'text', 'text' => 'Composite 2']]]
    ];
    expect(Messages::becomesComposite($mixedArray))->toBeTrue();
});
