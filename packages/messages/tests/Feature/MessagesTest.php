<?php

use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;

// Test HandlesCreation trait
test('can create Messages from string', function () {
    $messages = Messages::fromString('Hello');
    
    expect($messages)->toBeInstanceOf(Messages::class)
        ->and($messages->messageList()->count())->toBe(1)
        ->and($messages->first()->role()->value)->toBe('user')
        ->and($messages->first()->content()->toString())->toBe('Hello');
});

test('can create Messages from array', function () {
    $messages = Messages::fromArray([
        ['role' => 'user', 'content' => 'Hello'],
        ['role' => 'assistant', 'content' => 'Hi there']
    ]);
    
    expect($messages)->toBeInstanceOf(Messages::class)
        ->and($messages->messageList()->count())->toBe(2)
        ->and($messages->first()->role()->value)->toBe('user')
        ->and($messages->first()->content()->toString())->toBe('Hello')
        ->and($messages->last()->role()->value)->toBe('assistant')
        ->and($messages->last()->content()->toString())->toBe('Hi there');
});

test('can create Messages from Message objects', function () {
    $message1 = new Message('user', 'Hello');
    $message2 = new Message('assistant', 'Hi there');
    
    $messages = Messages::fromMessages([$message1, $message2]);
    
    expect($messages)->toBeInstanceOf(Messages::class)
        ->and($messages->messageList()->count())->toBe(2)
        ->and($messages->first()->role()->value)->toBe('user')
        ->and($messages->last()->role()->value)->toBe('assistant');
});

test('can create Messages from mixed array', function () {
    $messages = Messages::fromAnyArray([
        ['role' => 'user', 'content' => 'Hello'],
        'How are you?'
    ]);
    
    expect($messages)->toBeInstanceOf(Messages::class)
        ->and($messages->messageList()->count())->toBe(2)
        ->and($messages->first()->role()->value)->toBe('user')
        ->and($messages->first()->content()->toString())->toBe('Hello')
        ->and($messages->last()->role()->value)->toBe('user')
        ->and($messages->last()->content()->toString())->toBe('How are you?');
});

test('throws exception for invalid message array', function () {
    expect(fn() => Messages::fromArray([['invalid' => 'message']]))->toThrow(Exception::class);
});

// Test HandlesAccess trait
test('can access first and last messages', function () {
    $messages = Messages::fromArray([
        ['role' => 'user', 'content' => 'First'],
        ['role' => 'assistant', 'content' => 'Middle'],
        ['role' => 'user', 'content' => 'Last']
    ]);
    
    expect($messages->first()->content()->toString())->toBe('First')
        ->and($messages->last()->content()->toString())->toBe('Last');
});

test('exposes message list', function () {
    $messages = Messages::fromArray([
        ['role' => 'user', 'content' => 'Hello'],
        ['role' => 'assistant', 'content' => 'Hi']
    ]);

    $list = $messages->messageList();

    expect($list)->toBeInstanceOf(\Cognesy\Messages\MessageList::class)
        ->and($list->count())->toBe(2);
});

test('returns empty message for first/last when collection is empty', function () {
    $messages = Messages::empty();
    
    expect($messages->first())->toBeInstanceOf(Message::class)
        ->and($messages->first()->isEmpty())->toBeTrue()
        ->and($messages->last())->toBeInstanceOf(Message::class)
        ->and($messages->last()->isEmpty())->toBeTrue();
});

test('can iterate over messages with each', function () {
    $messages = Messages::fromArray([
        ['role' => 'user', 'content' => 'Hello'],
        ['role' => 'assistant', 'content' => 'Hi there']
    ]);
    
    $count = 0;
    foreach ($messages->each() as $message) {
        expect($message)->toBeInstanceOf(Message::class);
        $count++;
    }
    
    expect($count)->toBe(2);
});

test('can detect composite messages', function () {
    $simpleMessages = Messages::fromArray([
        ['role' => 'user', 'content' => 'Hello'],
        ['role' => 'assistant', 'content' => 'Hi there']
    ]);
    
    $compositeMessage = new Message('user', [
        ['type' => 'text', 'text' => 'Hello 1'],
        ['type' => 'text', 'text' => 'Hello 2'],
    ]);
    
    $withComposite = Messages::empty()
        ->appendMessages($simpleMessages)
        ->appendMessage($compositeMessage);
    
    expect($simpleMessages->hasComposites())->toBeFalse()
        ->and($withComposite->hasComposites())->toBeTrue();
});

test('can get middle messages', function () {
    $messages = Messages::fromArray([
        ['role' => 'user', 'content' => 'First'],
        ['role' => 'assistant', 'content' => 'Middle'],
        ['role' => 'user', 'content' => 'Last']
    ]);
});

test('can check if messages collection is empty', function () {
    $emptyMessages = Messages::empty();
    $nonEmptyMessages = Messages::fromString('Hello');
    
    expect($emptyMessages->isEmpty())->toBeTrue()
        ->and($emptyMessages->notEmpty())->toBeFalse()
        ->and($nonEmptyMessages->isEmpty())->toBeFalse()
        ->and($nonEmptyMessages->notEmpty())->toBeTrue();
});

test('can reduce messages', function () {
    $messages = Messages::fromArray([
        ['role' => 'user', 'content' => 'Hello'],
        ['role' => 'assistant', 'content' => 'Hi'],
        ['role' => 'user', 'content' => 'How are you?']
    ]);
    
    $totalLength = $messages->reduce(function ($carry, $message) {
        return $carry + strlen($message->content()->toString());
    }, 0);
    
    expect($totalLength)->toBe(strlen('Hello') + strlen('Hi') + strlen('How are you?'));
});

test('can map messages', function () {
    $messages = Messages::fromArray([
        ['role' => 'user', 'content' => 'hello'],
        ['role' => 'assistant', 'content' => 'hi']
    ]);
    
    $upperCaseContents = $messages->map(fn($message) => strtoupper($message->content()->toString()));
    
    expect($upperCaseContents)->toBe(['HELLO', 'HI']);
});

test('can filter messages', function () {
    $messages = Messages::fromArray([
        ['role' => 'user', 'content' => 'Hello'],
        ['role' => 'assistant', 'content' => 'Hi'],
        ['role' => 'user', 'content' => 'How are you?']
    ]);
    
    $userMessages = $messages->filter(fn($message) => $message->role()->value === 'user');
    
    expect($userMessages)->toBeInstanceOf(Messages::class)
        ->and($userMessages->messageList()->count())->toBe(2)
        ->and($userMessages->first()->content()->toString())->toBe('Hello')
        ->and($userMessages->last()->content()->toString())->toBe('How are you?');
});

// Test HandlesMutation trait
test('can set message', function () {
    $messages = Messages::empty();
    $messages = $messages->withMessage(new Message('user', 'Hello'));
    
    expect($messages->messageList()->count())->toBe(1)
        ->and($messages->first()->content()->toString())->toBe('Hello');
});

test('can set messages', function () {
    $messages = Messages::empty();
    $messagesToSet = Messages::fromArray([
        ['role' => 'user', 'content' => 'Hello'],
        ['role' => 'assistant', 'content' => 'Hi']
    ]);
    
    $messages = $messages->withMessages($messagesToSet);
    
    expect($messages->messageList()->count())->toBe(2)
        ->and($messages->first()->content()->toString())->toBe('Hello')
        ->and($messages->last()->content()->toString())->toBe('Hi');
});

test('can set messages from MessageList', function () {
    $messages = Messages::empty();
    $list = \Cognesy\Messages\MessageList::fromArray([
        new Message('user', 'Hello'),
        new Message('assistant', 'Hi'),
    ]);

    $messages = $messages->withMessages($list);

    expect($messages->messageList()->count())->toBe(2)
        ->and($messages->first()->content()->toString())->toBe('Hello')
        ->and($messages->last()->content()->toString())->toBe('Hi');
});

test('can append message', function () {
    $messages = Messages::fromString('Hello');
    $messages = $messages->appendMessage(new Message('assistant', 'Hi'));
    
    expect($messages->messageList()->count())->toBe(2)
        ->and($messages->last()->content()->toString())->toBe('Hi');
});

test('can append messages', function () {
    $messages = Messages::fromString('Hello');
    $messagesToAppend = Messages::fromArray([
        ['role' => 'assistant', 'content' => 'Hi'],
        ['role' => 'user', 'content' => 'How are you?']
    ]);
    
    $messages = $messages->appendMessages($messagesToAppend);
    
    expect($messages->messageList()->count())->toBe(3)
        ->and($messages->last()->content()->toString())->toBe('How are you?');
});

test('can append MessageList', function () {
    $messages = Messages::fromString('Hello');
    $list = \Cognesy\Messages\MessageList::fromArray([
        new Message('assistant', 'Hi'),
        new Message('user', 'How are you?'),
    ]);

    $messages = $messages->appendMessages($list);

    expect($messages->messageList()->count())->toBe(3)
        ->and($messages->last()->content()->toString())->toBe('How are you?');
});

test('can prepend message', function () {
    $messages = Messages::fromString('Hello');
    $messages = $messages->prependMessages(new Message('assistant', 'Hi'));
    
    expect($messages->messageList()->count())->toBe(2)
        ->and($messages->first()->content()->toString())->toBe('Hi');
});

test('can prepend messages', function () {
    $messages = Messages::fromString('Hello');
    $messagesToPrepend = Messages::fromArray([
        ['role' => 'assistant', 'content' => 'Hi'],
        ['role' => 'user', 'content' => 'How are you?']
    ]);
    
    $messages = $messages->prependMessages($messagesToPrepend);
    
    expect($messages->messageList()->count())->toBe(3)
        ->and($messages->first()->content()->toString())->toBe('Hi');
});

test('can remove head and tail', function () {
    $messages = Messages::fromArray([
        ['role' => 'user', 'content' => 'First'],
        ['role' => 'assistant', 'content' => 'Middle'],
        ['role' => 'user', 'content' => 'Last']
    ]);
    
    $messages = $messages->removeHead();
    expect($messages->messageList()->count())->toBe(2)
        ->and($messages->first()->content()->toString())->toBe('Middle');
    
    $messages = $messages->removeTail();
    expect($messages->messageList()->count())->toBe(1)
        ->and($messages->first()->content()->toString())->toBe('Middle');
});

test('exposes head and tail lists', function () {
    $messages = Messages::fromArray([
        ['role' => 'user', 'content' => 'First'],
        ['role' => 'assistant', 'content' => 'Middle'],
        ['role' => 'user', 'content' => 'Last']
    ]);

    $head = $messages->headList();
    $tail = $messages->tailList();

    expect($head)->toBeInstanceOf(\Cognesy\Messages\MessageList::class)
        ->and($head->count())->toBe(1)
        ->and($head->first()?->content()->toString())->toBe('First')
        ->and($tail->count())->toBe(1)
        ->and($tail->first()?->content()->toString())->toBe('Last');
});

// Test HandlesConversion trait
test('can convert messages to array', function () {
    $messages = Messages::fromArray([
        ['role' => 'user', 'content' => 'Hello'],
        ['role' => 'assistant', 'content' => 'Hi']
    ]);
    
    $array = $messages->toArray();
    
    expect($array)->toBeArray()
        ->and($array)->toHaveCount(2)
        ->and($array[0]['role'])->toBe('user')
        ->and($array[0]['content'])->toBe('Hello')
        ->and($array[1]['role'])->toBe('assistant')
        ->and($array[1]['content'])->toBe('Hi');
});

test('can convert messages to string', function () {
    $messages = Messages::fromArray([
        ['role' => 'user', 'content' => 'Hello'],
        ['role' => 'assistant', 'content' => 'Hi']
    ]);
    
    $string = $messages->toString();
    
    expect($string)->toBe("Hello\nHi\n");
});

// Test HandlesTransformation trait
test('can transform to merged per role', function () {
    $messages = Messages::fromArray([
        ['role' => 'user', 'content' => 'Hello'],
        ['role' => 'user', 'content' => 'How are you?'],
        ['role' => 'assistant', 'content' => 'I am fine'],
        ['role' => 'assistant', 'content' => 'Thank you for asking']
    ]);
    
    $merged = $messages->toMergedPerRole();
    
    expect($merged->messageList()->count())->toBe(2)
        ->and($merged->first()->role()->value)->toBe('user')
        ->and($merged->first()->content()->toString())->toContain('Hello')
        ->and($merged->first()->content()->toString())->toContain('How are you?')
        ->and($merged->last()->role()->value)->toBe('assistant')
        ->and($merged->last()->content()->toString())->toContain('I am fine')
        ->and($merged->last()->content()->toString())->toContain('Thank you for asking');
});

test('can filter messages by roles', function () {
    $messages = Messages::fromArray([
        ['role' => 'user', 'content' => 'Hello'],
        ['role' => 'assistant', 'content' => 'Hi'],
        ['role' => 'system', 'content' => 'System message']
    ]);
    
    $userMessages = $messages->forRoles(['user']);
    $nonSystemMessages = $messages->exceptRoles(['system']);
    
    expect($userMessages->messageList()->count())->toBe(1)
        ->and($userMessages->first()->role()->value)->toBe('user')
        ->and($nonSystemMessages->messageList()->count())->toBe(2)
        ->and($nonSystemMessages->first()->role()->value)->toBe('user')
        ->and($nonSystemMessages->last()->role()->value)->toBe('assistant');
});

// Template moved out of utils package, so this test is commented out
//test('can convert to role string', function () {
//    $messages = Messages::fromArray([
//        ['role' => 'user', 'content' => 'Hello'],
//        ['role' => 'assistant', 'content' => 'Hi']
//    ]);
//
//    $roleString = $messages->toRoleString();
//
//    expect($roleString)->toContain('user: Hello')
//        ->and($roleString)->toContain('assistant: Hi');
//});

test('can remap roles', function () {
    $messages = Messages::fromArray([
        ['role' => 'user', 'content' => 'Hello'],
        ['role' => 'assistant', 'content' => 'Hi'],
        ['role' => 'system', 'content' => 'System message']
    ]);
    
    $remapped = $messages->remapRoles([
        'user' => 'human',
        'assistant' => 'ai'
    ]);
    
    expect($remapped->first()->role()->value)->toBe('human')
        ->and($remapped->messageList()->get(1)?->role()->value)->toBe('ai')
        ->and($remapped->last()->role()->value)->toBe('system'); // Not in mapping, should stay the same
})->skip('Needs clarification on expected behavior');

test('can reverse messages', function () {
    $messages = Messages::fromArray([
        ['role' => 'user', 'content' => 'First'],
        ['role' => 'assistant', 'content' => 'Second']
    ]);
    
    $reversed = $messages->reversed();
    
    expect($reversed->first()->content()->toString())->toBe('Second')
        ->and($reversed->last()->content()->toString())->toBe('First');
});

test('appends content field to last message', function () {
    $messages = Messages::fromArray([
        ['role' => 'user', 'content' => 'First'],
        ['role' => 'assistant', 'content' => 'Second']
    ]);

    $updated = $messages->appendContentField('meta', 'value');

    expect($updated->last()->content()->toArray())->toBe([
        ['type' => 'text', 'text' => 'Second', 'meta' => 'value']
    ]);
});
