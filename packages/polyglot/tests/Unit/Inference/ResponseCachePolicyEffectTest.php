<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Contracts\CanManageStreamCache;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Http\Enums\StreamCachePolicy;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Creation\InferenceRequestBuilder;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Enums\ResponseCachePolicy;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\PendingInference;
use Cognesy\Polyglot\Tests\Support\FakeInferenceDriver;
use Cognesy\Polyglot\Tests\Support\TestConfig;

/**
 * What ResponseCachePolicy does, and — the point of this file — what it does NOT do
 * (instructor-eexl.23).
 *
 * The session used to hold a per-execution response cache keyed on this policy. It could
 * never return a value: InferenceExecution::response() is checked first in response(), and
 * the cache is only ever written from succeed(), which runs immediately after the execution
 * was set to withSuccessfulAttempt($response). The moment the cache holds something, the
 * execution holds the same instance, and nothing later clears it.
 *
 * That was measured, not reasoned: a counter on the cache over the full suite recorded
 * get() reached 390 times with 0 hits, and store() called 453 times with caching actually
 * enabled on exactly 1 of them. The cache is gone; the enum is not.
 *
 * The first test pins the behaviour the cache was supposed to provide, so that removing it
 * is provably a no-op — it passed identically before and after the deletion. It must stay
 * policy-parameterised: a version asserting only ::Memory would pass while ::None silently
 * grew a second driver call.
 *
 * The second test pins the meaning the enum still has, which is the reason it was not
 * deleted along with the cache. Without it the enum reads as vestigial in this package and
 * the next reader deletes it — its one remaining polyglot effect lives in a private match()
 * inside BaseInferenceRequestDriver that nothing else asserts.
 */

function cachePolicyPending(
    FakeInferenceDriver $driver,
    ResponseCachePolicy $policy,
    bool $streamed,
): PendingInference {
    $request = (new InferenceRequestBuilder())
        ->withMessages(Messages::fromString('Say hi'))
        ->withModel('test-model')
        ->withStreaming($streamed)
        ->withResponseCachePolicy($policy)
        ->create();

    return new PendingInference(
        execution: InferenceExecution::fromRequest($request),
        driver: $driver,
        eventDispatcher: new EventDispatcher(),
    );
}

function cachePolicyDriver(bool $streamed): FakeInferenceDriver {
    return $streamed
        ? new FakeInferenceDriver(onStream: fn() => [
            new PartialInferenceDelta(contentDelta: 'he'),
            new PartialInferenceDelta(contentDelta: 'llo', finishReason: 'stop'),
        ])
        : new FakeInferenceDriver(onResponse: fn() => new InferenceResponse(
            content: 'hi',
            finishReason: 'stop',
            usage: new InferenceUsage(inputTokens: 11, outputTokens: 7),
        ));
}

it('returns the same response instance from repeated response() calls, whatever the cache policy', function (
    ResponseCachePolicy $policy,
    bool $streamed,
) {
    $driver = cachePolicyDriver($streamed);
    $pending = cachePolicyPending($driver, $policy, $streamed);

    $first = $pending->response();
    $second = $pending->response();
    $third = $pending->response();

    // Identity, not equality: the guarantee is that the execution hands back the object it
    // already holds. toEqual() would also pass if the driver ran again and produced a twin.
    expect($second)->toBe($first);
    expect($third)->toBe($first);
    expect($first->content())->toBe($streamed ? 'hello' : 'hi');

    // The real proof there is no second execution.
    expect($driver->responseCalls + $driver->streamCalls)->toBe(1);
})->with([
    'none / sync' => [ResponseCachePolicy::None, false],
    'memory / sync' => [ResponseCachePolicy::Memory, false],
    'none / streamed' => [ResponseCachePolicy::None, true],
    'memory / streamed' => [ResponseCachePolicy::Memory, true],
]);

it('returns the same response after the stream was drained first, whatever the cache policy', function (
    ResponseCachePolicy $policy,
) {
    // The other route into response(): the caller iterated deltas, so the execution was
    // finalized by the stream callback rather than by the retry loop. Both routes wrote the
    // cache; both are served by the execution instead.
    $driver = cachePolicyDriver(streamed: true);
    $pending = cachePolicyPending($driver, $policy, streamed: true);

    iterator_to_array($pending->stream()->responses());

    $first = $pending->response();
    expect($pending->response())->toBe($first);
    expect($driver->streamCalls)->toBe(1);
})->with([
    'none' => [ResponseCachePolicy::None],
    'memory' => [ResponseCachePolicy::Memory],
]);

it('maps the request cache policy onto the HTTP stream cache policy', function (
    ResponseCachePolicy $requested,
    StreamCachePolicy $expected,
) {
    $seen = [];
    $recorder = new class($seen) implements CanManageStreamCache {
        /** @param array<int, StreamCachePolicy> $seen */
        public function __construct(public array &$seen) {}

        #[\Override]
        public function manage(HttpResponse $response, StreamCachePolicy $policy): HttpResponse {
            $this->seen[] = $policy;
            return $response;
        }
    };

    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://api.openai.com/v1/chat/completions')
        ->withStream(true)
        ->replySSEFromJson([
            ['choices' => [['delta' => ['content' => 'hi'], 'finish_reason' => 'stop']]],
        ], addDone: true);

    $runtime = InferenceRuntime::fromConfig(
        TestConfig::llm('openai'),
        httpClient: (new HttpClientBuilder())->withDriver($mock)->create(),
        streamCacheManager: $recorder,
    );

    $stream = Inference::fromRuntime($runtime)
        ->withModel('gpt-4o-mini')
        ->withMessages(Messages::fromString('Say hi'))
        ->withStreaming(true)
        ->withResponseCachePolicy($requested)
        ->stream();

    iterator_to_array($stream->responses());

    expect($recorder->seen)->toBe([$expected]);
})->with([
    'none' => [ResponseCachePolicy::None, StreamCachePolicy::None],
    'memory' => [ResponseCachePolicy::Memory, StreamCachePolicy::Memory],
]);
