<?php declare(strict_types=1);

use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Messages\MessageStore\Section;

describe('SectionMutator', function () {
    describe('appendMessages', function () {
        it('appends single message to empty section', function () {
            $store = new MessageStore();
            $systemMessage = new Message(role: 'system', content: 'You are helpful.');
            
            $result = $store->applyTo('system')->appendMessages($systemMessage);
            
            expect($result->sections()->names())->toBe(['system']);
            expect($result->section('system')->messages()->count())->toBe(1);
            expect($result->section('system')->messages()->first()->content()->toString())->toBe('You are helpful.');
        });

        it('appends multiple messages to existing section', function () {
            $store = new MessageStore();
            $userMessage = new Message(role: 'user', content: 'Hello');
            $assistantMessage = new Message(role: 'assistant', content: 'Hi there!');
            
            $store = $store->applyTo('chat')->appendMessages($userMessage);
            $result = $store->applyTo('chat')->appendMessages($assistantMessage);
            
            expect($result->section('chat')->messages()->count())->toBe(2);
            expect($result->section('chat')->messages()->first()->content()->toString())->toBe('Hello');
            expect($result->section('chat')->messages()->last()->content()->toString())->toBe('Hi there!');
        });

        it('appends Messages object with multiple messages', function () {
            $store = new MessageStore();
            $messages = Messages::fromArray([
                ['role' => 'user', 'content' => 'First'],
                ['role' => 'assistant', 'content' => 'Second'],
            ]);
            
            $result = $store->applyTo('conversation')->appendMessages($messages);
            
            expect($result->section('conversation')->messages()->count())->toBe(2);
        });

        it('appends message array', function () {
            $store = new MessageStore();
            $messageArray = ['role' => 'user', 'content' => 'Test message'];
            
            $result = $store->applyTo('test')->appendMessages($messageArray);
            
            expect($result->section('test')->messages()->count())->toBe(1);
            expect($result->section('test')->messages()->first()->content()->toString())->toBe('Test message');
        });

        it('ignores empty messages', function () {
            $store = new MessageStore();
            $emptyMessages = Messages::empty();
            
            $result = $store->applyTo('empty')->appendMessages($emptyMessages);
            
            expect($result->sections()->names())->toBe([]);
            expect($result)->toBe($store); // Should return same instance
        });
    });

    describe('replaceMessages', function () {
        it('replaces messages in existing section', function () {
            $store = new MessageStore();
            $userMessage = new Message(role: 'user', content: 'Hello');
            $assistantMessage = new Message(role: 'assistant', content: 'Hi there!');
            
            $store = $store->applyTo('chat')->appendMessages($userMessage);
            $result = $store->applyTo('chat')->setMessages($assistantMessage);
            
            expect($result->section('chat')->messages()->count())->toBe(1);
            expect($result->section('chat')->messages()->first()->role()->value)->toBe('assistant');
            expect($result->section('chat')->messages()->first()->content()->toString())->toBe('Hi there!');
        });

        it('creates section if it does not exist', function () {
            $store = new MessageStore();
            $systemMessage = new Message(role: 'system', content: 'You are helpful.');
            
            $result = $store->applyTo('new-section')->setMessages($systemMessage);
            
            expect($result->sections()->names())->toBe(['new-section']);
            expect($result->section('new-section')->messages()->count())->toBe(1);
        });
    });

    describe('remove', function () {
        it('removes existing section', function () {
            $store = new MessageStore();
            $userMessage = new Message(role: 'user', content: 'Hello');
            
            $store = $store->applyTo('temp')->appendMessages($userMessage);
            expect($store->sections()->names())->toBe(['temp']);
            
            $result = $store->applyTo('temp')->remove();
            
            expect($result->sections()->names())->toBe([]);
        });

        it('returns same store when removing non-existent section', function () {
            $store = new MessageStore();
            
            $result = $store->applyTo('nonexistent')->remove();
            
            expect($result)->toBe($store);
            expect($result->sections()->names())->toBe([]);
        });
    });

    describe('replaceSection', function () {
        it('replaces existing section with new section', function () {
            $store = new MessageStore();
            $userMessage = new Message(role: 'user', content: 'Hello');
            
            $store = $store->applyTo('original')->appendMessages($userMessage);
            $newSection = new Section('original', messages: Messages::fromArray([
                ['role' => 'assistant', 'content' => 'Replaced content']
            ]));
            
            $result = $store->applyTo('original')->setSection($newSection);
            
            expect($result->section('original')->messages()->count())->toBe(1);
            expect($result->section('original')->messages()->first()->role()->value)->toBe('assistant');
            expect($result->section('original')->messages()->first()->content()->toString())->toBe('Replaced content');
        });
    });

    describe('clear', function () {
        it('clears all messages but keeps section', function () {
            $store = new MessageStore();
            $userMessage = new Message(role: 'user', content: 'Hello');
            
            $store = $store->applyTo('toClear')->appendMessages($userMessage);
            expect($store->section('toClear')->messages()->count())->toBe(1);
            
            $result = $store->applyTo('toClear')->clear();
            
            expect($result->sections()->names())->toBe(['toClear']);
            expect($result->section('toClear')->messages()->count())->toBe(0);
            expect($result->section('toClear')->isEmpty())->toBeTrue();
        });
    });

    describe('chaining operations', function () {
        it('allows chaining multiple operations', function () {
            $store = new MessageStore();
            $systemMessage = new Message(role: 'system', content: 'You are helpful.');
            $userMessage = new Message(role: 'user', content: 'Hello');
            $assistantMessage = new Message(role: 'assistant', content: 'Hi there!');
            
            $result = $store
                ->applyTo('system')->appendMessages($systemMessage)
                ->applyTo('chat')->appendMessages($userMessage)
                ->applyTo('chat')->appendMessages($assistantMessage)
                ->applyTo('system')->clear();
                
            expect($result->sections()->names())->toBe(['system', 'chat']);
            expect($result->section('system')->messages()->count())->toBe(0);
            expect($result->section('chat')->messages()->count())->toBe(2);
        });
    });

    describe('immutability', function () {
        it('does not modify original store', function () {
            $store = new MessageStore();
            $systemMessage = new Message(role: 'system', content: 'You are helpful.');
            $originalSectionCount = count($store->sections()->names());
            $store->applyTo('test')->appendMessages($systemMessage);
            expect(count($store->sections()->names()))->toBe($originalSectionCount);
        });

        it('returns new MessageStore instance for each operation', function () {
            $store = new MessageStore();
            $systemMessage = new Message(role: 'system', content: 'You are helpful.');
            $userMessage = new Message(role: 'user', content: 'Hello');
            
            $result1 = $store->applyTo('test1')->appendMessages($systemMessage);
            $result2 = $result1->applyTo('test2')->appendMessages($userMessage);
            
            expect($result1)->not->toBe($store);
            expect($result2)->not->toBe($result1);
            expect($result2)->not->toBe($store);
        });
    });
});