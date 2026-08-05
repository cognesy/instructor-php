<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\InferenceRetryPolicy;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptStarted;
use Cognesy\Polyglot\Inference\PendingInference;
use Cognesy\Polyglot\Telemetry\MessagesSerializationMemo;
use Cognesy\Polyglot\Tests\Support\FakeInferenceDriver;
use Cognesy\Telemetry\Domain\Envelope\TelemetryEnvelope;

/**
 * The telemetry path serialises the conversation at four sites per non-retried request. The
 * memo cuts that to one.
 *
 * `Messages` and `Message` are both `final readonly`, so `toArray()` cannot be intercepted by
 * a test double -- the same wall eexl.4 hit. These tests read
 * `MessagesSerializationMemo::serialisationCount()` instead, as a delta rather than an
 * absolute, so they hold in a shared process.
 */

function memoConversation(): Messages {
    return Messages::fromArray([
        ['role' => 'system', 'content' => 'You are terse.'],
        ['role' => 'user', 'content' => 'What is the capital of France?'],
    ]);
}

/** @param list<InferenceResponse> $responses */
function runMemoSession(Messages $messages, EventDispatcher $events, array $responses, string $lengthRecovery = 'none'): void {
    $pending = new PendingInference(
        execution: InferenceExecution::fromRequest(new InferenceRequest(
            messages: $messages,
            model: 'gpt-memo',
            retryPolicy: new InferenceRetryPolicy(
                maxAttempts: 2,
                baseDelayMs: 0,
                lengthRecovery: $lengthRecovery,
                lengthMaxAttempts: 1,
                lengthContinuePrompt: 'Continue.',
            ),
        )),
        driver: new FakeInferenceDriver(responses: $responses),
        eventDispatcher: $events,
    );

    $pending->response();
}

function okResponse(string $content = 'Paris.'): InferenceResponse {
    return new InferenceResponse(
        content: $content,
        finishReason: 'stop',
        usage: new InferenceUsage(inputTokens: 10, outputTokens: 2),
    );
}

it('serialises the conversation once, not four times, across a whole request', function () {
    $events = new EventDispatcher();
    $events->wiretap(static function (): void {});

    $before = MessagesSerializationMemo::serialisationCount();
    runMemoSession(memoConversation(), $events, [okResponse()]);
    $after = MessagesSerializationMemo::serialisationCount();

    // Four envelope-building sites run under a wiretap: execution() at started and
    // completed, attempt() at attempt-started and attempt-succeeded.
    expect($after - $before)->toBe(1);
});

it('returns the same content as the unmemoized call', function () {
    $messages = memoConversation();

    expect(MessagesSerializationMemo::toArray($messages))->toBe($messages->toArray());
});

it('serialises again for a different instance carrying identical content', function () {
    // Identity keying, not content keying -- two equal conversations are two serialisations.
    MessagesSerializationMemo::toArray(memoConversation());

    $before = MessagesSerializationMemo::serialisationCount();
    MessagesSerializationMemo::toArray(memoConversation());

    expect(MessagesSerializationMemo::serialisationCount() - $before)->toBe(1);
});

it('does not keep the conversation alive', function () {
    $messages = memoConversation();
    $probe = WeakReference::create($messages);
    MessagesSerializationMemo::toArray($messages);

    unset($messages);

    expect($probe->get())->toBeNull();
});

it('re-serialises when a length-recovery retry rewrites the conversation', function () {
    // The reason the memo must key on identity: buildLengthRecoveryRequest() replaces the
    // conversation mid-session, and a session-scoped cache would serve the stale one.
    $events = new EventDispatcher();
    $inputs = [];
    $events->addListener(InferenceAttemptStarted::class, function (InferenceAttemptStarted $e) use (&$inputs): void {
        $inputs[] = $e->data[TelemetryEnvelope::KEY]['io']['input'] ?? null;
    });

    runMemoSession(
        memoConversation(),
        $events,
        [
            new InferenceResponse(content: 'Par', finishReason: 'length', usage: new InferenceUsage(inputTokens: 10, outputTokens: 1)),
            okResponse('Paris.'),
        ],
        lengthRecovery: 'continue',
    );

    expect($inputs)->toHaveCount(2)
        ->and($inputs[0])->toHaveCount(2);

    // The rewrite appends the partial assistant turn and the continue prompt.
    expect($inputs[1])->toHaveCount(4)
        ->and($inputs[1][2]['content'])->toBe('Par')
        ->and($inputs[1][3]['content'])->toBe('Continue.');
});
