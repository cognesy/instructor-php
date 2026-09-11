<?php declare(strict_types=1);

use Cognesy\Messages\ContentPart;
use Cognesy\Messages\ContentParts;
use Cognesy\Messages\Message;
use Cognesy\Polyglot\Inference\Assembly\ReplayAccumulator;
use Cognesy\Polyglot\Inference\Data\ReplayEnvelope;

it('accumulates replay data in first-seen semantic order and replaces keyed data', function () {
    $replay = new ReplayAccumulator('test-provider');
    $replay->rememberResponse(['id' => 'response-1']);
    $replay->remember('text');
    $replay->remember('reasoning', ['signature' => 'first']);
    $replay->remember('tool');
    $replay->remember('reasoning', ['signature' => 'final']);
    $replay->remember('text');

    expect($replay->envelope()?->toArray())->toBe([
        'version' => ReplayEnvelope::CURRENT_VERSION,
        'owner' => 'test-provider',
        'response' => ['id' => 'response-1'],
        'parts' => [
            null,
            ['signature' => 'final'],
            null,
        ],
    ]);
});

it('does not create replay metadata without provider-private part data', function () {
    $replay = new ReplayAccumulator('test-provider');
    $replay->rememberResponse(['id' => 'response-only']);
    $replay->remember('text');

    expect($replay->envelope())->toBeNull();
});

it('extracts only owned aligned replay data from a message', function () {
    $parts = new ContentParts(ContentPart::reasoning('Think.'), ContentPart::text('Answer.'));
    $envelope = ReplayEnvelope::fromParts('test-provider', [['signature' => 'signed'], null]);
    $message = new Message(
        role: 'assistant',
        parts: $parts,
        metadata: [ReplayEnvelope::MESSAGE_METADATA_KEY => $envelope?->toArray()],
    );

    expect(ReplayEnvelope::fromMessage($message, 'test-provider'))->not->toBeNull()
        ->and(ReplayEnvelope::fromMessage($message, 'other-provider'))->toBeNull()
        ->and(ReplayEnvelope::fromMessage(
            $message->withMetadata(ReplayEnvelope::MESSAGE_METADATA_KEY, [
                'version' => ReplayEnvelope::CURRENT_VERSION,
                'owner' => 'test-provider',
                'parts' => [['signature' => 'misaligned']],
            ]),
            'test-provider',
        ))->toBeNull();
});

it('retains replay parts only when the keep mask uses the same alignment invariant', function () {
    $envelope = ReplayEnvelope::fromParts(
        'test-provider',
        [['signature' => 'signed'], null, ['item' => 'tool']],
    );

    expect($envelope?->retaining([true, false, true])?->parts())->toBe([
        ['signature' => 'signed'],
        ['item' => 'tool'],
    ])->and($envelope?->retaining([true]))->toBeNull();
});
