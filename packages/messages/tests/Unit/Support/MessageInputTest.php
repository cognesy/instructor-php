<?php

use Cognesy\Messages\Enums\MessageRole;
use Cognesy\Messages\Message;

describe('MessageInput', function () {
    describe('message shape precedence (regression: instructor-r50t.24)', function () {
        it('does not turn field values into content when content is absent', function () {
            $message = Message::fromArray(['role' => 'user', 'name' => 'bob']);

            expect($message->content()->isEmpty())->toBeTrue();
            expect($message->name())->toBe('bob');
            expect($message->role())->toBe(MessageRole::User);
        });

        it('does not use the role string as content for a role-only array', function () {
            $message = Message::fromArray(['role' => 'assistant']);

            expect($message->content()->isEmpty())->toBeTrue();
            expect($message->role())->toBe(MessageRole::Assistant);
        });

        it('does not absorb identity fields into content', function () {
            $message = Message::fromArray([
                'role' => 'user',
                'id' => '11111111-1111-4111-8111-111111111111',
            ]);

            expect($message->content()->isEmpty())->toBeTrue();
            expect((string) $message->id())->toBe('11111111-1111-4111-8111-111111111111');
        });

        it('still reads content from a normal message array', function () {
            $message = Message::fromArray(['role' => 'user', 'content' => 'hi']);

            expect($message->content()->toString())->toBe('hi');
        });

        it('still reads content when other string fields are present', function () {
            $message = Message::fromArray(['role' => 'user', 'content' => 'hi', 'name' => 'bob']);

            expect($message->content()->toString())->toBe('hi');
            expect($message->name())->toBe('bob');
        });

        it('accepts a content-only array and defaults the role', function () {
            $message = Message::fromArray(['content' => 'hi']);

            expect($message->content()->toString())->toBe('hi');
            expect($message->role())->toBe(MessageRole::User);
        });

        it('still reads a bare list of strings as text parts', function () {
            $message = Message::fromArray(['a', 'b']);

            expect($message->content()->partsList()->count())->toBe(2);
            expect($message->content()->toString())->toBe("a\nb");
        });

        it('rejects a keyed array that is not a message', function () {
            expect(fn() => Message::fromArray(['foo' => 'bar']))
                ->toThrow(InvalidArgumentException::class);
        });
    });

    describe('role validation (regression: instructor-r50t.2)', function () {
        it('throws at construction for an unknown role', function () {
            expect(fn() => new Message('human', 'hi'))
                ->toThrow(InvalidArgumentException::class, 'Invalid message role: human');
        });

        it('throws when hydrating an array with an unknown role', function () {
            expect(fn() => Message::fromArray(['role' => 'human', 'content' => 'hi']))
                ->toThrow(InvalidArgumentException::class, 'Invalid message role: human');
        });

        it('throws from withRole for an unknown role', function () {
            $message = new Message('user', 'hi');

            expect(fn() => $message->withRole('human'))
                ->toThrow(InvalidArgumentException::class, 'Invalid message role: human');
        });

        it('does not silently coerce an unknown role to user', function () {
            expect(fn() => Message::fromAny('hi', 'human'))
                ->toThrow(InvalidArgumentException::class, 'Invalid message role: human');
        });

        it('normalizes an empty role to the default', function () {
            expect((new Message('', 'hi'))->role())->toBe(MessageRole::User);
            expect((new Message(null, 'hi'))->role())->toBe(MessageRole::User);
        });

        it('round-trips every valid role through construct, toArray, fromArray, role', function () {
            foreach (MessageRole::cases() as $role) {
                $original = new Message($role, 'hi');
                $restored = Message::fromArray($original->toArray());

                expect($restored->role())->toBe($role);
            }
        });

        it('accepts a MessageRole enum in withRole', function () {
            $message = (new Message('user', 'hi'))->withRole(MessageRole::Assistant);

            expect($message->role())->toBe(MessageRole::Assistant);
        });
    });

    describe('becomesComposite (regression: instructor-r50t.11)', function () {
        it('returns false without warning for an array with no content key', function () {
            expect(Message::becomesComposite(['role' => 'user']))->toBeFalse();
        });

        it('returns false for scalar content', function () {
            expect(Message::becomesComposite(['role' => 'user', 'content' => 'hi']))->toBeFalse();
        });

        it('returns true for array content', function () {
            expect(Message::becomesComposite(['role' => 'user', 'content' => [['type' => 'text', 'text' => 'hi']]]))->toBeTrue();
        });
    });
});
