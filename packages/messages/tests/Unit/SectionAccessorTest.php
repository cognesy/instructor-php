<?php declare(strict_types=1);

use Cognesy\Messages\Message;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Messages\MessageStore\Operators\SectionOperator;

describe('SectionAccessor', function () {
    describe('name', function () {
        it('returns section name', function () {
            $store = new MessageStore();
            $accessor = new SectionOperator($store, 'test-section');
            
            expect($accessor->name())->toBe('test-section');
        });
    });

    describe('get', function () {
        it('returns existing section', function () {
            $store = new MessageStore();
            $message = new Message(role: 'user', content: 'Hello');
            $store = $store->section('chat')->appendMessages($message);
            
            $accessor = new SectionOperator($store, 'chat');
            $section = $accessor->get();
            
            expect($section->name)->toBe('chat');
            expect($section->messages()->count())->toBe(1);
            expect($section->messages()->first()->content()->toString())->toBe('Hello');
        });

        it('returns empty section for non-existing section', function () {
            $store = new MessageStore();
            $accessor = new SectionOperator($store, 'nonexistent');
            
            $section = $accessor->get();
            
            expect($section->name)->toBe('nonexistent');
            expect($section->isEmpty())->toBeTrue();
            expect($section->messages()->count())->toBe(0);
        });
    });

    describe('exists', function () {
        it('returns true for existing section', function () {
            $store = new MessageStore();
            $message = new Message(role: 'system', content: 'You are helpful.');
            $store = $store->section('system')->appendMessages($message);
            
            $accessor = new SectionOperator($store, 'system');
            
            expect($accessor->exists())->toBeTrue();
        });

        it('returns false for non-existing section', function () {
            $store = new MessageStore();
            $accessor = new SectionOperator($store, 'nonexistent');
            
            expect($accessor->exists())->toBeFalse();
        });

        it('returns false for empty store', function () {
            $store = new MessageStore();
            $accessor = new SectionOperator($store, 'any-section');
            
            expect($accessor->exists())->toBeFalse();
        });
    });

    describe('isEmpty', function () {
        it('returns true for empty section', function () {
            $store = new MessageStore();
            $accessor = new SectionOperator($store, 'empty');
            
            expect($accessor->isEmpty())->toBeTrue();
        });

        it('returns true for non-existent section', function () {
            $store = new MessageStore();
            $accessor = new SectionOperator($store, 'nonexistent');
            
            expect($accessor->isEmpty())->toBeTrue();
        });

        it('returns false for section with messages', function () {
            $store = new MessageStore();
            $message = new Message(role: 'user', content: 'Hello');
            $store = $store->section('chat')->appendMessages($message);
            
            $accessor = new SectionOperator($store, 'chat');
            
            expect($accessor->isEmpty())->toBeFalse();
        });

        it('returns true after section is cleared', function () {
            $store = new MessageStore();
            $message = new Message(role: 'user', content: 'Hello');
            $store = $store->section('chat')->appendMessages($message);
            $store = $store->section('chat')->clear();
            
            $accessor = new SectionOperator($store, 'chat');
            
            expect($accessor->isEmpty())->toBeTrue();
        });
    });

    describe('isNotEmpty', function () {
        it('returns false for empty section', function () {
            $store = new MessageStore();
            $accessor = new SectionOperator($store, 'empty');
            
            expect($accessor->isNotEmpty())->toBeFalse();
        });

        it('returns false for non-existent section', function () {
            $store = new MessageStore();
            $accessor = new SectionOperator($store, 'nonexistent');
            
            expect($accessor->isNotEmpty())->toBeFalse();
        });

        it('returns true for section with messages', function () {
            $store = new MessageStore();
            $message = new Message(role: 'user', content: 'Hello');
            $store = $store->section('chat')->appendMessages($message);
            
            $accessor = new SectionOperator($store, 'chat');
            
            expect($accessor->isNotEmpty())->toBeTrue();
        });

        it('returns false after section is cleared', function () {
            $store = new MessageStore();
            $message = new Message(role: 'user', content: 'Hello');
            $store = $store->section('chat')->appendMessages($message);
            $store = $store->section('chat')->clear();
            
            $accessor = new SectionOperator($store, 'chat');
            
            expect($accessor->isNotEmpty())->toBeFalse();
        });
    });

    describe('messages', function () {
        it('returns messages from existing section', function () {
            $store = new MessageStore();
            $message1 = new Message(role: 'user', content: 'Hello');
            $message2 = new Message(role: 'assistant', content: 'Hi there!');
            $store = $store->section('chat')->appendMessages([$message1, $message2]);
            
            $accessor = new SectionOperator($store, 'chat');
            $messages = $accessor->messages();
            
            expect($messages->count())->toBe(2);
            expect($messages->first()->content()->toString())->toBe('Hello');
            expect($messages->last()->content()->toString())->toBe('Hi there!');
        });

        it('returns empty messages for non-existing section', function () {
            $store = new MessageStore();
            $accessor = new SectionOperator($store, 'nonexistent');
            
            $messages = $accessor->messages();
            
            expect($messages->isEmpty())->toBeTrue();
            expect($messages->count())->toBe(0);
        });

        it('returns empty messages for empty section', function () {
            $store = new MessageStore();
            $message = new Message(role: 'user', content: 'Hello');
            $store = $store->section('chat')->appendMessages($message);
            $store = $store->section('chat')->clear();
            
            $accessor = new SectionOperator($store, 'chat');
            $messages = $accessor->messages();
            
            expect($messages->isEmpty())->toBeTrue();
            expect($messages->count())->toBe(0);
        });
    });

    describe('immutability', function () {
        it('does not modify original store', function () {
            $store = new MessageStore();
            $message = new Message(role: 'user', content: 'Hello');
            $store = $store->section('chat')->appendMessages($message);
            
            $accessor = new SectionOperator($store, 'chat');
            $originalSectionCount = count($store->sections()->names());
            
            // Calling accessor methods should not modify the store
            $accessor->exists();
            $accessor->isEmpty();
            $accessor->isNotEmpty();
            $accessor->get();
            $accessor->messages();
            
            expect(count($store->sections()->names()))->toBe($originalSectionCount);
            expect($store->section('chat')->messages()->count())->toBe(1);
        });

        it('returns consistent results across multiple calls', function () {
            $store = new MessageStore();
            $message = new Message(role: 'user', content: 'Hello');
            $store = $store->section('chat')->appendMessages($message);
            
            $accessor = new SectionOperator($store, 'chat');
            
            // Multiple calls should return consistent results
            expect($accessor->exists())->toBeTrue();
            expect($accessor->exists())->toBeTrue();
            expect($accessor->isEmpty())->toBeFalse();
            expect($accessor->isEmpty())->toBeFalse();
            expect($accessor->isNotEmpty())->toBeTrue();
            expect($accessor->isNotEmpty())->toBeTrue();
            expect($accessor->messages()->count())->toBe(1);
            expect($accessor->messages()->count())->toBe(1);
        });
    });

    describe('multiple accessors', function () {
        it('can create multiple accessors for different sections', function () {
            $store = new MessageStore();
            $systemMessage = new Message(role: 'system', content: 'You are helpful.');
            $userMessage = new Message(role: 'user', content: 'Hello');
            
            $store = $store->section('system')->appendMessages($systemMessage);
            $store = $store->section('chat')->appendMessages($userMessage);
            
            $systemAccessor = new SectionOperator($store, 'system');
            $chatAccessor = new SectionOperator($store, 'chat');
            $nonExistentAccessor = new SectionOperator($store, 'nonexistent');
            
            expect($systemAccessor->exists())->toBeTrue();
            expect($systemAccessor->isNotEmpty())->toBeTrue();
            expect($systemAccessor->messages()->count())->toBe(1);
            
            expect($chatAccessor->exists())->toBeTrue();
            expect($chatAccessor->isNotEmpty())->toBeTrue();
            expect($chatAccessor->messages()->count())->toBe(1);
            
            expect($nonExistentAccessor->exists())->toBeFalse();
            expect($nonExistentAccessor->isEmpty())->toBeTrue();
            expect($nonExistentAccessor->messages()->count())->toBe(0);
        });
    });
});