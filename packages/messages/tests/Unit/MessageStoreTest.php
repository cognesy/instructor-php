<?php declare(strict_types=1);

use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\MessageStore;

describe('MessageStore', function () {
    describe('withMessagesAppendedInSection', function () {
        it('creates section and adds messages to empty store', function () {
            $store = new MessageStore();
            $message = new Message(role: 'system', content: 'You are helpful.');
            
            $result = $store->section('system')->appendMessages($message);
            
            expect($result->sections()->names())->toBe(['system']);
            expect($result->section('system')->messages()->count())->toBe(1);
            expect($result->section('system')->messages()->first()->role()->value)->toBe('system');
            expect($result->section('system')->messages()->first()->content()->toString())->toBe('You are helpful.');
        });
        
        it('appends messages to existing section', function () {
            $existingMessage = new Message(role: 'user', content: 'Hello');
            $store = (new MessageStore())->section('chat')->appendMessages($existingMessage);
            
            $newMessage = new Message(role: 'assistant', content: 'Hi there!');
            $result = $store->section('chat')->appendMessages($newMessage);
            
            expect($result->sections()->names())->toBe(['chat']);
            expect($result->section('chat')->messages()->count())->toBe(2);
            expect($result->section('chat')->messages()->first()->content()->toString())->toBe('Hello');
            expect($result->section('chat')->messages()->last()->content()->toString())->toBe('Hi there!');
        });
        
        it('handles Messages object with multiple messages', function () {
            $store = new MessageStore();
            $messages = Messages::fromArray([
                ['role' => 'system', 'content' => 'System prompt'],
                ['role' => 'user', 'content' => 'User message'],
            ]);
            
            $result = $store->section('conversation')->appendMessages($messages);
            
            expect($result->sections()->names())->toBe(['conversation']);
            expect($result->section('conversation')->messages()->count())->toBe(2);
            expect($result->section('conversation')->messages()->first()->role()->value)->toBe('system');
            expect($result->section('conversation')->messages()->last()->role()->value)->toBe('user');
        });
        
        it('ignores empty messages', function () {
            $store = new MessageStore();
            $emptyMessages = Messages::empty();
            
            $result = $store->section('empty')->appendMessages($emptyMessages);
            
            expect($result->sections()->names())->toBe([]);
            expect($result)->toBe($store); // Should return same instance
        });
        
        it('handles message array input', function () {
            $store = new MessageStore();
            $messageArray = ['role' => 'user', 'content' => 'Test message'];
            
            $result = $store->section('test')->appendMessages($messageArray);
            
            expect($result->sections()->names())->toBe(['test']);
            expect($result->section('test')->messages()->count())->toBe(1);
            expect($result->section('test')->messages()->first()->content()->toString())->toBe('Test message');
        });
    });
    
    describe('section creation and access', function () {
        it('returns empty section for non-existent section', function () {
            $store = new MessageStore();
            
            $section = $store->section('nonexistent')->get();
            
            expect($section->isEmpty())->toBeTrue();
            expect($section->name)->toBe('nonexistent');
        });
        
        it('creates section with withSection', function () {
            $store = new MessageStore();
            
            $result = $store->withSection('newsection');
            
            expect($result->sections()->names())->toBe(['newsection']);
            expect($result->section('newsection')->isEmpty())->toBeTrue();
        });
    });
    
    describe('regression tests', function () {
        it('prevents regression where withMessagesAppendedInSection fails on empty store', function () {
            // This test specifically prevents the bug where withSectionReplaced
            // would not add new sections to an empty store
            $store = new MessageStore();
            $systemMessage = new Message(role: 'system', content: 'You are helpful.');
            $promptMessage = new Message(role: 'user', content: 'Say hi.');
            
            // This should work even when starting with completely empty store
            $withSystem = $store->section('system')->appendMessages($systemMessage);
            $withPrompt = $withSystem->section('prompt')->appendMessages($promptMessage);
            
            expect($withPrompt->sections()->names())->toContain('system');
            expect($withPrompt->sections()->names())->toContain('prompt');
            expect($withPrompt->section('system')->messages()->count())->toBe(1);
            expect($withPrompt->section('prompt')->messages()->count())->toBe(1);
            
            // Verify content is preserved correctly
            $systemSection = $withPrompt->section('system')->get();
            $promptSection = $withPrompt->section('prompt')->get();
            
            expect($systemSection->messages()->first()->content()->toString())->toBe('You are helpful.');
            expect($promptSection->messages()->first()->content()->toString())->toBe('Say hi.');
        });
    });

    describe('serialization', function () {
        it('round-trips sections, messages, and parameters', function () {
            $store = MessageStore::fromSections(
                new \Cognesy\Messages\MessageStore\Section('system'),
                new \Cognesy\Messages\MessageStore\Section('chat'),
            );
            $store = $store->parameters()->withParams(['locale' => 'en']);
            $store = $store->section('system')->appendMessages([
                'role' => 'system',
                'content' => 'You are helpful.',
                '_metadata' => ['tool_call_id' => 'call_1'],
            ]);
            $store = $store->section('chat')->appendMessages([
                'role' => 'user',
                'content' => 'Hello',
            ]);

            $serialized = $store->toArray();
            $restored = MessageStore::fromArray($serialized);

            expect($restored->sections()->names())->toBe(['system', 'chat']);
            expect($restored->section('system')->messages()->first()->metadata()->toArray())
                ->toBe(['tool_call_id' => 'call_1']);
            expect($restored->section('chat')->messages()->first()->content()->toString())
                ->toBe('Hello');
            expect($restored->parameters()->get()->toArray())->toBe(['locale' => 'en']);
        });
    });
});
