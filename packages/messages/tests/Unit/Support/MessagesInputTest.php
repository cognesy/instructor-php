<?php declare(strict_types=1);

use Cognesy\Messages\Message;
use Cognesy\Messages\MessageList;
use Cognesy\Messages\Messages;
use Cognesy\Messages\Support\MessagesInput;
use Cognesy\Messages\Tests\Fixtures\StubMessageProvider;
use Cognesy\Messages\Tests\Fixtures\StubMessagesProvider;

describe('MessagesInput', function () {
    describe('fromArray', function () {
        it('turns a list of strings into user messages', function () {
            $messages = MessagesInput::fromArray(['hello', 'world']);

            expect($messages->count())->toBe(2);
            expect($messages->first()->role()->value)->toBe('user');
            expect($messages->first()->content()->toString())->toBe('hello');
            expect($messages->last()->content()->toString())->toBe('world');
        });

        it('turns a list of message arrays into messages', function () {
            $messages = MessagesInput::fromArray([
                ['role' => 'user', 'content' => 'a'],
                ['role' => 'assistant', 'content' => 'b'],
            ]);

            expect($messages->count())->toBe(2);
            expect($messages->first()->role()->value)->toBe('user');
            expect($messages->last()->role()->value)->toBe('assistant');
            expect($messages->last()->content()->toString())->toBe('b');
        });

        it('accepts a mix of strings and message arrays', function () {
            $messages = MessagesInput::fromArray([
                'plain string',
                ['role' => 'system', 'content' => 'be helpful'],
            ]);

            expect($messages->count())->toBe(2);
            expect($messages->first()->role()->value)->toBe('user');
            expect($messages->last()->role()->value)->toBe('system');
        });

        it('returns an empty Messages for an empty list', function () {
            $messages = MessagesInput::fromArray([]);

            expect($messages->isEmpty())->toBeTrue();
            expect($messages->count())->toBe(0);
        });

        it('rejects a keyed array without role or content keys', function () {
            expect(fn() => MessagesInput::fromArray([['foo' => 'bar']]))
                ->toThrow(InvalidArgumentException::class, 'Invalid message array - missing role or content keys');
        });

        it('rejects a content-only array element because it has no role key', function () {
            // Message::isMessage() (used here) requires a 'role' key, unlike
            // MessageInput::fromArray()'s isMessageShape(), which defaults the role
            // for a content-only array. A bare ['content' => 'hi'] list element is
            // therefore rejected by fromArray() even though Message::fromArray()
            // called directly on the same array succeeds and defaults the role.
            expect(fn() => MessagesInput::fromArray([['content' => 'hi']]))
                ->toThrow(InvalidArgumentException::class, 'Invalid message array - missing role or content keys');
        });
    });

    describe('fromAnyArray', function () {
        it('wraps a single message array into a one-message collection', function () {
            $messages = MessagesInput::fromAnyArray(['role' => 'user', 'content' => 'hi']);

            expect($messages->count())->toBe(1);
            expect($messages->first()->content()->toString())->toBe('hi');
        });

        it('turns a list of strings into user messages via direct construction', function () {
            $messages = MessagesInput::fromAnyArray(['hello', 'world']);

            expect($messages->count())->toBe(2);
            expect($messages->first()->role()->value)->toBe('user');
            expect($messages->first()->content()->toString())->toBe('hello');
        });

        it('turns a list of message arrays into messages', function () {
            $messages = MessagesInput::fromAnyArray([
                ['role' => 'user', 'content' => 'a'],
                ['role' => 'assistant', 'content' => 'b'],
            ]);

            expect($messages->count())->toBe(2);
            expect($messages->last()->role()->value)->toBe('assistant');
        });

        it('passes through Message instances unchanged', function () {
            $message = new Message(role: 'user', content: 'existing');

            $messages = MessagesInput::fromAnyArray([$message]);

            expect($messages->count())->toBe(1);
            expect($messages->first())->toBe($message);
        });

        it('accepts a mix of strings, message arrays and Message instances', function () {
            $existing = new Message(role: 'tool', content: 'result');

            $messages = MessagesInput::fromAnyArray([
                'plain',
                ['role' => 'assistant', 'content' => 'reply'],
                $existing,
            ]);

            expect($messages->count())->toBe(3);
            expect($messages->all()[0]->role()->value)->toBe('user');
            expect($messages->all()[1]->role()->value)->toBe('assistant');
            expect($messages->all()[2])->toBe($existing);
        });

        it('returns an empty Messages for an empty list', function () {
            $messages = MessagesInput::fromAnyArray([]);

            expect($messages->isEmpty())->toBeTrue();
        });

        it('rejects an array element with keys but no role or content', function () {
            expect(fn() => MessagesInput::fromAnyArray([['foo' => 'bar']]))
                ->toThrow(InvalidArgumentException::class, 'Invalid message array - missing role or content keys');
        });

        it('rejects a non-array, non-string, non-Message element', function () {
            expect(fn() => MessagesInput::fromAnyArray([42]))
                ->toThrow(InvalidArgumentException::class, 'Invalid message type');
        });
    });

    describe('fromAny', function () {
        it('parses a string as a single-message Messages via Messages::fromString', function () {
            $messages = MessagesInput::fromAny('hello');

            expect($messages->count())->toBe(1);
            expect($messages->first()->role()->value)->toBe('user');
            expect($messages->first()->content()->toString())->toBe('hello');
        });

        it('delegates an array to fromAnyArray', function () {
            $messages = MessagesInput::fromAny([
                ['role' => 'user', 'content' => 'a'],
                ['role' => 'assistant', 'content' => 'b'],
            ]);

            expect($messages->count())->toBe(2);
        });

        it('wraps a single Message into a Messages collection', function () {
            $message = new Message(role: 'assistant', content: 'hi');

            $messages = MessagesInput::fromAny($message);

            expect($messages->count())->toBe(1);
            expect($messages->first())->toBe($message);
        });

        it('returns a Messages instance unchanged (same object)', function () {
            $original = new Messages(new Message(role: 'user', content: 'a'));

            $result = MessagesInput::fromAny($original);

            expect($result)->toBe($original);
        });

        it('converts a MessageList into a Messages collection', function () {
            $m1 = new Message(role: 'user', content: 'a');
            $m2 = new Message(role: 'assistant', content: 'b');
            $list = new MessageList($m1, $m2);

            $messages = MessagesInput::fromAny($list);

            expect($messages->count())->toBe(2);
            expect($messages->all())->toBe([$m1, $m2]);
        });
    });

    describe('fromInput', function () {
        it('returns a Messages instance unchanged (same object)', function () {
            $original = new Messages(new Message(role: 'user', content: 'a'));

            $result = MessagesInput::fromInput($original);

            expect($result)->toBe($original);
        });

        it('converts a MessageList into a Messages collection', function () {
            $m1 = new Message(role: 'user', content: 'a');
            $list = new MessageList($m1);

            $result = MessagesInput::fromInput($list);

            expect($result->count())->toBe(1);
            expect($result->first())->toBe($m1);
        });

        it('delegates to CanProvideMessages::toMessages()', function () {
            $result = MessagesInput::fromInput(new StubMessagesProvider());

            expect($result->count())->toBe(1);
            expect($result->first()->content()->toString())->toBe('From messages provider');
        });

        it('wraps a single Message into a Messages collection', function () {
            $message = new Message(role: 'assistant', content: 'hi');

            $result = MessagesInput::fromInput($message);

            expect($result->count())->toBe(1);
            expect($result->first())->toBe($message);
        });

        it('delegates to CanProvideMessage::toMessage() and wraps the result', function () {
            $result = MessagesInput::fromInput(new StubMessageProvider());

            expect($result->count())->toBe(1);
            expect($result->first()->content()->toString())->toBe('From message provider');
        });

        it('wraps a plain string as a single user message via TextRepresentation', function () {
            $result = MessagesInput::fromInput('hello');

            expect($result->count())->toBe(1);
            expect($result->first()->role()->value)->toBe('user');
            expect($result->first()->content()->toString())->toBe('hello');
        });

        it('JSON-encodes a plain array as content via TextRepresentation', function () {
            $result = MessagesInput::fromInput(['a' => 1]);

            expect($result->count())->toBe(1);
            expect($result->first()->content()->toString())->toBe('{"a":1}');
        });

        it('JSON-encodes an arbitrary object with no known conversion method', function () {
            $result = MessagesInput::fromInput(new stdClass());

            expect($result->count())->toBe(1);
            expect($result->first()->content()->toString())->toBe('{}');
        });
    });
});
