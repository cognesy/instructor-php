<?php

declare(strict_types=1);

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\Support\RequestMessages;

it('merges consecutive roles only when alternating roles are not supported', function () {
    $request = new InferenceRequest(messages: Messages::fromArray([
        ['role' => 'user', 'content' => 'First'],
        ['role' => 'user', 'content' => 'Second'],
        ['role' => 'assistant', 'content' => 'Third'],
    ]));

    expect(RequestMessages::forMapping($request, true)->count())->toBe(3)
        ->and(RequestMessages::forMapping($request, false)->count())->toBe(2)
        ->and(RequestMessages::forMapping($request, false)->first()?->content()->toString())->toContain('First');
});

it('extracts role text from plain and composite messages', function () {
    $messages = Messages::fromArray([
        ['role' => 'system', 'content' => 'First instruction'],
        ['role' => 'developer', 'content' => [
            ['type' => 'text', 'text' => 'Second instruction'],
            ['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,abc']],
        ]],
        ['role' => 'user', 'content' => 'Ignored'],
    ]);

    expect(RequestMessages::textForRoles($messages, ['system', 'developer']))
        ->toBe("First instruction\n\nSecond instruction");
});

it('filters messages by excluded roles', function () {
    $messages = Messages::fromArray([
        ['role' => 'system', 'content' => 'Rule'],
        ['role' => 'user', 'content' => 'Question'],
    ]);

    $filtered = RequestMessages::exceptRoles($messages, ['system']);

    expect($filtered->count())->toBe(1)
        ->and($filtered->first()?->role()->value)->toBe('user');
});
