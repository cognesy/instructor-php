<?php declare(strict_types=1);

use Cognesy\Messages\ContentPart;
use Cognesy\Polyglot\Inference\Assembly\AssistantMessageParseResult;
it('constructs semantic and replay projections from one aligned pair of lists', function () {
    $parsed = AssistantMessageParseResult::fromParts(
        owner: 'test-provider',
        parts: [
            ContentPart::reasoning('Think.'),
            ContentPart::text('Answer.'),
        ],
        replayParts: [
            ['signature' => 'signed'],
            null,
        ],
        response: ['id' => 'response-1'],
    );

    expect($parsed->parts()->toArray())->toBe([
        ['type' => 'reasoning', 'text' => 'Think.'],
        ['type' => 'text', 'text' => 'Answer.'],
    ])->and($parsed->replay()?->parts())->toBe([
        ['signature' => 'signed'],
        null,
    ])->and($parsed->replay()?->response())->toBe(['id' => 'response-1']);
});

it('rejects independently constructed projections that are not aligned', function () {
    AssistantMessageParseResult::fromParts(
        owner: 'test-provider',
        parts: [ContentPart::text('Answer.')],
        replayParts: [],
    );
})->throws(\InvalidArgumentException::class, 'Semantic and replay parts must be positionally aligned.');
