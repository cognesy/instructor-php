<?php declare(strict_types=1);

use Cognesy\Events\Contracts\CanCheckListeners;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\PendingHttpResponse;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceRequest;
use Cognesy\Polyglot\Inference\Contracts\CanTranslateInferenceResponse;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Drivers\BaseInferenceRequestDriver;
use Cognesy\Polyglot\Inference\Events\InferenceRequested;
use Cognesy\Polyglot\Inference\Events\InferenceResponseCreated;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * BaseInferenceRequestDriver must not assemble lifecycle event payloads when the
 * dispatcher reports no listeners, and must still emit everything when the
 * dispatcher cannot report its listeners at all.
 *
 * The payload builders are private, so they cannot be overridden to count calls.
 * Instead the probe request exposes an accessor that THROWS, reached only from
 * inside those builders. If a guard is removed, these tests fail with the
 * sentinel exception rather than a soft assertion.
 * `tests/Regression/InferenceRequestedPayloadRegressionTest.php` uses the same
 * technique to prove the full request is never materialised.
 */

/** A dispatcher that reports no listeners for anything. */
final class SilentCheckingDispatcher implements EventDispatcherInterface, CanCheckListeners
{
    /** @var list<object> */
    public array $dispatched = [];

    public function dispatch(object $event): object {
        $this->dispatched[] = $event;
        return $event;
    }

    #[\Override]
    public function hasListenersFor(string $eventClass): bool {
        return false;
    }
}

/** A plain PSR-14 dispatcher -- no CanCheckListeners, so it must be assumed to listen. */
final class OpaquePsrDispatcher implements EventDispatcherInterface
{
    /** @var list<object> */
    public array $dispatched = [];

    public function dispatch(object $event): object {
        $this->dispatched[] = $event;
        return $event;
    }
}

/**
 * A request whose model() throws. Both requestEventData() and responseEventData()
 * read it, and nothing else on this test's path does, so entering either builder
 * raises the sentinel.
 *
 * (InferenceResponse is final, so the response side cannot be probed directly --
 * this is why the probe lives on the request.)
 */
function gateProbeRequest(): InferenceRequest {
    return new class(messages: Messages::fromString('hello')) extends InferenceRequest {
        #[\Override]
        public function model(): string {
            throw new RuntimeException('Event payload must not be built with no listeners.');
        }
    };
}

function gateProbeDriver(EventDispatcherInterface $events, InferenceResponse $response): BaseInferenceRequestDriver {
    return new class(
        new LLMConfig(),
        new class implements CanSendHttpRequests {
            public function send(HttpRequest $request): PendingHttpResponse {
                return new PendingHttpResponse($request, new class implements CanHandleHttpRequest {
                    public function handle(HttpRequest $request): HttpResponse {
                        return HttpResponse::sync(statusCode: 200, headers: [], body: '{"ok":true}');
                    }
                });
            }
        },
        $events,
        new class implements CanTranslateInferenceRequest {
            public function toHttpRequest(InferenceRequest $request): HttpRequest {
                return new HttpRequest(
                    url: 'https://example.test/inference',
                    method: 'POST',
                    headers: [],
                    body: [],
                    options: [],
                );
            }
        },
        new class($response) implements CanTranslateInferenceResponse {
            public function __construct(private InferenceResponse $response) {}

            public function fromResponse(HttpResponse $response): ?InferenceResponse {
                return $this->response;
            }

            public function fromStreamDeltas(iterable $eventBodies, ?HttpResponse $responseData = null): iterable {
                return [];
            }

            public function toEventBody(string $data): string|bool {
                return $data;
            }
        },
    ) extends BaseInferenceRequestDriver {};
}

it('builds no lifecycle payloads when the dispatcher reports no listeners', function () {
    $events = new SilentCheckingDispatcher();

    // The probe request throws if either payload builder is entered.
    gateProbeDriver($events, new InferenceResponse(content: 'ok', finishReason: 'stop'))
        ->makeResponseFor(gateProbeRequest());

    expect($events->dispatched)->toBeEmpty();
});

it('still emits every event to a dispatcher that cannot report its listeners', function () {
    $events = new OpaquePsrDispatcher();

    // Plain InferenceRequest/InferenceResponse -- the payload builders MUST run here.
    $response = gateProbeDriver($events, new InferenceResponse(content: 'ok', finishReason: 'stop'))
        ->makeResponseFor(new InferenceRequest(messages: Messages::fromString('hello')));

    expect($response->content())->toBe('ok');

    $classes = array_map(fn(object $e) => $e::class, $events->dispatched);
    expect($classes)->toContain(InferenceRequested::class)
        ->and($classes)->toContain(InferenceResponseCreated::class);
});

it('still emits every event when listeners are actually registered', function () {
    $events = new EventDispatcher();
    $seen = [];
    $events->addListener(InferenceRequested::class, function () use (&$seen): void { $seen[] = 'requested'; });
    $events->addListener(InferenceResponseCreated::class, function () use (&$seen): void { $seen[] = 'created'; });

    gateProbeDriver($events, new InferenceResponse(content: 'ok', finishReason: 'stop'))
        ->makeResponseFor(new InferenceRequest(messages: Messages::fromString('hello')));

    expect($seen)->toBe(['requested', 'created']);
});
