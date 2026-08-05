<?php

use Cognesy\Messages\Enums\MessageRole;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Messages\ToolCall;
use Cognesy\Messages\ToolCalls;
use Cognesy\Messages\ToolResult;

describe('toMergedPerRole (regression: instructor-r50t.3)', function () {
    it('merges consecutive same-role messages into one', function () {
        $messages = Messages::empty()
            ->appendMessage(new Message('user', 'a'))
            ->appendMessage(new Message('user', 'b'))
            ->appendMessage(new Message('assistant', 'c'));

        $merged = $messages->toMergedPerRole();

        expect($merged->count())->toBe(2);
        expect($merged->first()->role())->toBe(MessageRole::User);
        expect($merged->first()->content()->toString())->toBe("a\nb");
        expect($merged->last()->content()->toString())->toBe('c');
    });

    it('preserves the identity of the first message in a run', function () {
        $first = new Message('user', 'a');
        $second = new Message('user', 'b');
        $messages = Messages::empty()->appendMessage($first)->appendMessage($second);

        $merged = $messages->toMergedPerRole();

        expect((string) $merged->first()->id())->toBe((string) $first->id());
        expect($merged->first()->createdAt)->toEqual($first->createdAt);
    });

    it('keeps tool calls from every merged message', function () {
        $one = (new Message('assistant', 'a'))->withToolCalls(
            new ToolCalls(ToolCall::fromArray(['id' => 'call_1', 'name' => 'f', 'arguments' => '{}'])),
        );
        $two = (new Message('assistant', 'b'))->withToolCalls(
            new ToolCalls(ToolCall::fromArray(['id' => 'call_2', 'name' => 'g', 'arguments' => '{}'])),
        );

        $merged = Messages::empty()->appendMessage($one)->appendMessage($two)->toMergedPerRole();

        expect($merged->count())->toBe(1);
        expect($merged->first()->toolCalls()->count())->toBe(2);
        expect(array_values(array_map(
            fn(ToolCall $call) => $call->idString(),
            $merged->first()->toolCalls()->all(),
        )))->toBe(['call_1', 'call_2']);
    });

    it('never merges messages carrying a tool result', function () {
        $one = (new Message('tool', '{"temp":21}'))->withToolResult(
            ToolResult::success('{"temp":21}', 'call_1'),
        );
        $two = (new Message('tool', '{"temp":22}'))->withToolResult(
            ToolResult::success('{"temp":22}', 'call_2'),
        );

        $merged = Messages::empty()->appendMessage($one)->appendMessage($two)->toMergedPerRole();

        expect($merged->count())->toBe(2);
        expect($merged->first()->toolResult()?->callIdString())->toBe('call_1');
        expect($merged->last()->toolResult()?->callIdString())->toBe('call_2');
    });

    it('merges metadata with the later message winning on conflict', function () {
        $one = (new Message('user', 'a'))->withMetadata('k', 1)->withMetadata('keep', 'x');
        $two = (new Message('user', 'b'))->withMetadata('k', 2);

        $merged = Messages::empty()->appendMessage($one)->appendMessage($two)->toMergedPerRole();

        expect($merged->first()->metadata()->get('k'))->toBe(2);
        expect($merged->first()->metadata()->get('keep'))->toBe('x');
    });

    it('returns empty for empty input', function () {
        expect(Messages::empty()->toMergedPerRole()->isEmpty())->toBeTrue();
    });

    it('leaves alternating roles untouched', function () {
        $messages = Messages::empty()
            ->appendMessage(new Message('user', 'a'))
            ->appendMessage(new Message('assistant', 'b'))
            ->appendMessage(new Message('user', 'c'));

        expect($messages->toMergedPerRole()->count())->toBe(3);
    });
});

describe('withMergedFrom (regression: instructor-r50t.3)', function () {
    it('refuses to merge a message carrying a tool result', function () {
        $plain = new Message('assistant', 'a');
        $withResult = (new Message('tool', 'r'))->withToolResult(ToolResult::success('r', 'call_1'));

        expect(fn() => $plain->withMergedFrom($withResult))
            ->toThrow(InvalidArgumentException::class, 'tool result');
        expect(fn() => $withResult->withMergedFrom($plain))
            ->toThrow(InvalidArgumentException::class, 'tool result');
    });

    it('keeps the target role and name', function () {
        $target = (new Message('assistant', 'a'))->withName('bot');
        $source = new Message('user', 'b');

        $result = $target->withMergedFrom($source);

        expect($result->role())->toBe(MessageRole::Assistant);
        expect($result->name())->toBe('bot');
        expect($result->content()->toString())->toBe("a\nb");
    });
});

describe('asString renderer contract (regression: instructor-r50t.15)', function () {
    it('applies the separator to default rendering', function () {
        $rendered = Messages::asString([
            ['role' => 'user', 'content' => 'a'],
            ['role' => 'assistant', 'content' => 'b'],
        ], "\n");

        expect($rendered)->toBe("a\nb\n");
    });

    it('does not inject the separator into custom renderer output', function () {
        $rendered = Messages::asString(
            [
                ['role' => 'user', 'content' => 'a'],
                ['role' => 'assistant', 'content' => 'b'],
            ],
            '---SEPARATOR---',
            fn(array $message) => "<{$message['role']}>",
        );

        expect($rendered)->toBe('<user><assistant>');
        expect($rendered)->not->toContain('---SEPARATOR---');
    });
});
