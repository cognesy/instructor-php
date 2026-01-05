<?php

use Cognesy\Messages\Message;
use Cognesy\Messages\MessageList;

describe('MessageList', function () {
    it('creates empty list', function () {
        $list = MessageList::empty();
        expect($list->count())->toBe(0)
            ->and($list->isEmpty())->toBeTrue();
    });

    it('adds messages immutably', function () {
        $list = MessageList::empty();
        $next = $list->add(new Message('user', 'Hello'));

        expect($list->count())->toBe(0)
            ->and($next->count())->toBe(1)
            ->and($next->first()?->toString())->toBe('Hello');
    });

    it('replaces last message immutably', function () {
        $list = MessageList::fromArray([
            new Message('user', 'Hello'),
            new Message('assistant', 'World'),
        ]);

        $next = $list->replaceLast(new Message('assistant', 'Done'));

        expect($list->last()?->toString())->toBe('World')
            ->and($next->last()?->toString())->toBe('Done');
    });

    it('removes head and tail', function () {
        $list = MessageList::fromArray([
            new Message('user', 'First'),
            new Message('user', 'Middle'),
            new Message('user', 'Last'),
        ]);

        $headRemoved = $list->removeHead();
        $tailRemoved = $list->removeTail();

        expect($headRemoved->first()?->toString())->toBe('Middle')
            ->and($tailRemoved->last()?->toString())->toBe('Middle');
    });

    it('appends and prepends message lists', function () {
        $first = MessageList::fromArray([
            new Message('user', 'First'),
        ]);
        $second = MessageList::fromArray([
            new Message('assistant', 'Second'),
        ]);

        $appended = $first->addAll($second);
        $prepended = $first->prependAll($second);

        expect($appended->count())->toBe(2)
            ->and($appended->last()?->toString())->toBe('Second')
            ->and($prepended->first()?->toString())->toBe('Second');
    });

    it('filters out empty messages', function () {
        $list = MessageList::fromArray([
            new Message('user', 'Hello'),
            new Message('assistant', ''),
            new Message('user', 'World'),
        ]);

        $filtered = $list->withoutEmpty();

        expect($filtered->count())->toBe(2)
            ->and($filtered->first()?->toString())->toBe('Hello')
            ->and($filtered->last()?->toString())->toBe('World');
    });

    it('reverses list', function () {
        $list = MessageList::fromArray([
            new Message('user', 'First'),
            new Message('user', 'Second'),
        ]);

        $reversed = $list->reversed();

        expect($reversed->first()?->toString())->toBe('Second')
            ->and($reversed->last()?->toString())->toBe('First');
    });

    it('converts to array', function () {
        $list = MessageList::fromArray([
            new Message('user', 'Hello'),
            new Message('assistant', 'World'),
        ]);

        expect($list->toArray())->toBe([
            ['role' => 'user', 'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'World'],
        ]);
    });
});
