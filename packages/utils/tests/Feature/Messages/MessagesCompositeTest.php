<?php

use Cognesy\Utils\Messages\Message;
use Cognesy\Utils\Messages\Messages;

test('can merge roles with composite messages', function () {
    // Create a composite message
    $compositeMessage = new Message('user', [
        ['type' => 'text', 'text' => 'Hello from composite']
    ]);
    
    // Create a collection with regular and composite messages
    $messages = Messages::fromArray([
        ['role' => 'user', 'content' => 'Regular message'],
        ['role' => 'user', 'content' => 'Another regular message']
    ]);
    $messages->appendMessage($compositeMessage);
    
    // Merge roles
    $merged = $messages->toMergedPerRole();

    // Regular messages should be merged, composite message should stay separate
    expect($merged->all())->toHaveCount(1);
    expect($merged->first()->toString())->toContain('Regular message');
    expect($merged->first()->toString())->toContain('Another regular message');
    expect($merged->first()->toString())->toContain('Hello from composite');
    expect($merged->first()->isComposite())->toBeTrue();
});

test('can merge many roles with composite messages', function () {
    // Create a composite message
    $compositeMessage = new Message('system', [
        ['type' => 'text', 'text' => 'Hello from composite 1'],
        ['type' => 'text', 'text' => 'Hello from composite 2']
    ]);

    // Create a collection with regular and composite messages
    $messages = Messages::fromArray([
        ['role' => 'user', 'content' => 'Regular message'],
        ['role' => 'user', 'content' => 'Another regular message']
    ]);
    $messages->appendMessage($compositeMessage);

    // Merge roles
    $merged = $messages->toMergedPerRole();

    // Regular messages should be merged, composite message should stay separate
    expect($merged->all())->toHaveCount(2);
    expect($merged->first()->toString())->toContain('Regular message');
    expect($merged->first()->toString())->toContain('Another regular message');
    expect($merged->last()->toString())->toContain("Hello from composite 1\nHello from composite 2");
    expect($merged->last()->isComposite())->toBeTrue();
});

test('can merge multiple composite messages with the same role', function () {
    // Create composite messages
    $composite1 = new Message('user', [
        ['type' => 'text', 'text' => 'First composite']
    ]);
    $composite2 = new Message('user', [
        ['type' => 'text', 'text' => 'Second composite']
    ]);
    
    // Add to collection
    $messages = (new Messages())
        ->appendMessage($composite1)
        ->appendMessage($composite2);

    // Merge roles
    $merged = $messages->toMergedPerRole();

    // Should merge composite messages with same role
    expect($merged->all())->toHaveCount(1)
        ->and($merged->first()->isComposite())->toBeTrue()
        ->and($merged->first()->contentParts())->toBeArray()
        ->and(count($merged->contentParts()))->toBe(2);
});

test('merges roles correctly with mixed content types', function () {
    // Create a regular message
    $regular = new Message('user', 'Regular message');
    
    // Create a composite message
    $composite = new Message('user', [
        ['type' => 'text', 'text' => 'Composite message']
    ]);
    
    // Add regular then composite
    $messages1 = (new Messages())
        ->appendMessage($regular)
        ->appendMessage($composite);
    
    $merged1 = $messages1->toMergedPerRole();

    // Should have two messages - merged regular and separate composite
    expect($merged1->all())->toHaveCount(1)
        ->and($merged1->first()->isComposite())->toBeTrue();
    
    // Add composite then regular
    $messages2 = (new Messages())
        ->appendMessage($composite)
        ->appendMessage($regular);
    
    $merged2 = $messages2->toMergedPerRole();
    
    // Should have two messages again, but in different order
    expect($merged2->all())->toHaveCount(1)
        ->and($merged2->first()->isComposite())->toBeTrue();
});

test('correctly handles toAllComposites transformation', function () {
    // This tests the internal method by testing its effect
    
    $messages = Messages::fromArray([
        ['role' => 'user', 'content' => 'Regular message'],
        ['role' => 'assistant', 'content' => 'Another regular message']
    ]);
    
    // Create a composite message
    $compositeMessage = new Message('user', [
        ['type' => 'text', 'text' => 'Composite message'],
        ['type' => 'text', 'text' => 'Composite message']
    ]);
    $messages->appendMessage($compositeMessage);
    
    // Force merging all as composites by creating all composite messages
    $merged = $messages->toMergedPerRole();
    
    // Verify all messages are present
    expect($merged->all())->not->toBeEmpty();
    
    // Check if composite message structure is preserved
    $hasComposite = false;
    foreach ($merged->all() as $message) {
        if ($message->isComposite()) {
            $hasComposite = true;
            break;
        }
    }
    
    expect($hasComposite)->toBeTrue();
});

test('converts messages to role string format correctly', function () {
    // Create messages with different roles
    $messages = Messages::fromArray([
        ['role' => 'user', 'content' => 'User message'],
        ['role' => 'assistant', 'content' => 'Assistant message'],
        ['role' => 'system', 'content' => 'System message']
    ]);
    
    $roleString = $messages->toRoleString();
    
    expect($roleString)->toContain('user: User message')
        ->and($roleString)->toContain('assistant: Assistant message')
        ->and($roleString)->toContain('system: System message');
});

test('mergeRolesFlat correctly handles composite messages', function () {
    // This tests the internal mergeRolesFlat method by testing its effects
    
    // Create regular messages
    $messages = Messages::fromArray([
        ['role' => 'user', 'content' => 'Message 1'],
        ['role' => 'user', 'content' => 'Message 2']
    ]);
    
    // Add a composite message
    $compositeMessage = new Message('system', [
        ['type' => 'image_url', 'url' => 'https://example.com/image.png']
    ]);
    $messages->appendMessage($compositeMessage);

    // Add another regular message
    $messages->appendMessage(new Message('user', 'Message 3'));

    // Merge the roles
    $merged = $messages->toMergedPerRole();

    // Should have 2 messages:
    // 1. Merged "Message 1" and "Message 2"
    // 2. Composite message (preserved, but empty)
    // 3. "Message 3" (after composite)
    expect($merged->all())->toHaveCount(3)
        ->and($merged->all()[0]->isComposite())->toBeTrue()
        ->and($merged->all()[0]->toString())->toContain('Message 1')
        ->and($merged->all()[0]->toString())->toContain('Message 2')
        ->and($merged->all()[1]->isComposite())->toBeTrue()
        ->and($merged->all()[1]->toString())->toBe('')
        ->and($merged->all()[2]->toString())->toBe('Message 3');
});

test('handles role transitions in mergeRolesFlat correctly', function () {
    // Create messages with role transitions
    $messages = Messages::fromArray([
        ['role' => 'user', 'content' => 'User message 1'],
        ['role' => 'user', 'content' => 'User message 2'],
        ['role' => 'assistant', 'content' => 'Assistant message 1'],
        ['role' => 'assistant', 'content' => 'Assistant message 2'],
        ['role' => 'user', 'content' => 'User message 3']
    ]);
    
    // Merge the roles
    $merged = $messages->toMergedPerRole();
    
    // Should have 3 messages with merged content for each role transition
    expect($merged->all())->toHaveCount(3)
        ->and($merged->all()[0]->role()->value)->toBe('user')
        ->and($merged->all()[0]->content()->toString())->toContain('User message 1')
        ->and($merged->all()[0]->content()->toString())->toContain('User message 2')
        ->and($merged->all()[1]->role()->value)->toBe('assistant')
        ->and($merged->all()[1]->content()->toString())->toContain('Assistant message 1')
        ->and($merged->all()[1]->content()->toString())->toContain('Assistant message 2')
        ->and($merged->all()[2]->role()->value)->toBe('user')
        ->and($merged->all()[2]->content()->toString())->toBe('User message 3');
});

test('forRoles and exceptRoles handle composite messages properly', function () {
    // Create a composite message
    $compositeMessage = new Message('user', [
        ['type' => 'text', 'text' => 'Composite user message'],
        ['type' => 'text', 'text' => 'Composite user message']
    ]);
    
    // Create a collection with regular and composite messages
    $messages = Messages::fromArray([
        ['role' => 'user', 'content' => 'Regular user message'],
        ['role' => 'assistant', 'content' => 'Assistant message']
    ]);
    $messages->appendMessage($compositeMessage);
    
    // Filter for user messages
    $userMessages = $messages->forRoles(['user']);
    
    // Should include both regular and composite user messages
    expect($userMessages->all())->toHaveCount(2)
        ->and($userMessages->all()[0]->role()->value)->toBe('user')
        ->and($userMessages->all()[0]->content()->toString())->toBe('Regular user message')
        ->and($userMessages->all()[1]->role()->value)->toBe('user')
        ->and($userMessages->all()[1]->isComposite())->toBeTrue();
    
    // Filter to exclude user messages
    $nonUserMessages = $messages->exceptRoles(['user']);
    
    // Should only include assistant message
    expect($nonUserMessages->all())->toHaveCount(1)
        ->and($nonUserMessages->all()[0]->role()->value)->toBe('assistant');
});

test('remapRoles handles composite messages properly', function () {
    // Create a composite message
    $compositeMessage = new Message('user', [
        ['type' => 'text', 'text' => 'Composite user message'],
        ['type' => 'text', 'text' => 'Another part of composite']
    ]);
    
    // Create a collection with regular and composite messages
    $messages = Messages::fromArray([
        ['role' => 'user', 'content' => 'Regular user message'],
        ['role' => 'assistant', 'content' => 'Assistant message']
    ]);
    $messages->appendMessage($compositeMessage);
    
    // Remap roles
    $remapped = $messages->remapRoles([
        'user' => 'developer',
        'assistant' => 'system'
    ]);

    // Check that all messages have remapped roles, including composite
    expect($remapped->all())->toHaveCount(3)
        ->and($remapped->all()[0]->role()->value)->toBe('developer')
        ->and($remapped->all()[1]->role()->value)->toBe('system')
        ->and($remapped->all()[2]->role()->value)->toBe('developer')
        ->and($remapped->all()[2]->isComposite())->toBeTrue();
});
