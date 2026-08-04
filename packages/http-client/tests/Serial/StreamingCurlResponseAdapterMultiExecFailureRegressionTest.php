<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Curl {
    final class StreamingCurlExecFailureHook
    {
        public static bool $forceExecFailure = false;
        public static bool $forceComplete = false;
        public static bool $forceTransferFailure = false;
    }

    function curl_multi_exec(\CurlMultiHandle $multiHandle, int &$stillRunning): int {
        if (StreamingCurlExecFailureHook::$forceExecFailure) {
            $stillRunning = 1;
            return CURLM_BAD_HANDLE;
        }
        if (StreamingCurlExecFailureHook::$forceComplete) {
            $stillRunning = 0;
            return CURLM_OK;
        }

        return \curl_multi_exec($multiHandle, $stillRunning);
    }

    function curl_multi_info_read(\CurlMultiHandle $multiHandle): array|false {
        if (StreamingCurlExecFailureHook::$forceTransferFailure) {
            StreamingCurlExecFailureHook::$forceTransferFailure = false;
            return [
                'msg' => CURLMSG_DONE,
                'result' => CURLE_OPERATION_TIMEDOUT,
                'handle' => null,
            ];
        }

        return \curl_multi_info_read($multiHandle);
    }
}

namespace {
    use Cognesy\Events\Dispatchers\EventDispatcher;
    use Cognesy\Http\Drivers\Curl\CurlHandle;
    use Cognesy\Http\Drivers\Curl\HeaderParser;
    use Cognesy\Http\Drivers\Curl\StreamingCurlExecFailureHook;
    use Cognesy\Http\Drivers\Curl\StreamingCurlResponseAdapter;
    use Cognesy\Http\Data\HttpRequest;
    use Cognesy\Http\Events\HttpResponseChunkReceived;
    use Cognesy\Http\Events\HttpStreamCompleted;
    use Cognesy\Http\Exceptions\NetworkException;
    use PHPUnit\Framework\Assert;

    // Overrides curl functions in the Curl namespace + uses static hook state;
    // not safe under parallel scheduling — runs in the fast lane's serial pass.
    uses()->group('serial');

    it('throws when curl_multi_exec fails during streaming instead of ending silently', function () {
        if (!extension_loaded('curl')) {
            Assert::markTestSkipped('cURL extension not available');
        }

        $handle = CurlHandle::create('http://127.0.0.1:65535', 'GET');
        $multi = curl_multi_init();
        curl_multi_add_handle($multi, $handle->native());

        $headerParser = new HeaderParser();
        $headerParser->parse("HTTP/1.1 200 OK\r\n");
        $headerParser->parse("Content-Type: application/octet-stream\r\n");

        $adapter = new StreamingCurlResponseAdapter(
            handle: $handle,
            multi: $multi,
            queue: new \SplQueue(),
            headerParser: $headerParser,
            events: new EventDispatcher(),
            chunkSize: 64,
        );

        StreamingCurlExecFailureHook::$forceExecFailure = true;
        try {
            expect(fn() => iterator_to_array($adapter->toHttpResponse()->stream()))
                ->toThrow(NetworkException::class);
        } finally {
            StreamingCurlExecFailureHook::$forceExecFailure = false;
        }
    });

    it('splits queued curl stream buffers by configured chunk size', function () {
        if (!extension_loaded('curl')) {
            Assert::markTestSkipped('cURL extension not available');
        }

        $events = new EventDispatcher();
        $captured = [];
        $events->wiretap(static function (object $event) use (&$captured): void {
            $captured[] = $event;
        });

        $handle = CurlHandle::create('http://127.0.0.1:65535', 'GET');
        $multi = curl_multi_init();
        curl_multi_add_handle($multi, $handle->native());

        $queue = new \SplQueue();
        $queue->enqueue('abcdefg');

        $headerParser = new HeaderParser();
        $headerParser->parse("HTTP/1.1 200 OK\r\n");
        $headerParser->parse("Content-Type: application/octet-stream\r\n");

        $adapter = new StreamingCurlResponseAdapter(
            handle: $handle,
            multi: $multi,
            queue: $queue,
            headerParser: $headerParser,
            events: $events,
            requestId: 'req-curl-chunks',
            chunkSize: 3,
        );

        StreamingCurlExecFailureHook::$forceComplete = true;
        try {
            $chunks = iterator_to_array($adapter->toHttpResponse()->stream());
        } finally {
            StreamingCurlExecFailureHook::$forceComplete = false;
        }

        $chunkEvents = array_values(array_filter(
            $captured,
            static fn(object $event): bool => $event instanceof HttpResponseChunkReceived,
        ));
        $completed = array_values(array_filter(
            $captured,
            static fn(object $event): bool => $event instanceof HttpStreamCompleted,
        ));

        expect($chunks)->toBe(['abc', 'def', 'g'])
            ->and(array_map(static fn(HttpResponseChunkReceived $event): string => $event->data['chunk'], $chunkEvents))->toBe(['abc', 'def', 'g'])
            ->and($completed)->toHaveCount(1)
            ->and($completed[0]->data['bytes'])->toBe(7)
            ->and($completed[0]->data['chunks'])->toBe(3);
    });

    it('throws mapped transfer errors and reports failed stream completion', function () {
        if (!extension_loaded('curl')) {
            Assert::markTestSkipped('cURL extension not available');
        }

        $events = new EventDispatcher();
        $captured = [];
        $events->wiretap(static function (object $event) use (&$captured): void {
            $captured[] = $event;
        });

        $handle = CurlHandle::create('http://127.0.0.1:65535', 'GET');
        $multi = curl_multi_init();
        curl_multi_add_handle($multi, $handle->native());

        $headerParser = new HeaderParser();
        $headerParser->parse("HTTP/1.1 200 OK\r\n");

        $adapter = new StreamingCurlResponseAdapter(
            handle: $handle,
            multi: $multi,
            queue: new \SplQueue(),
            headerParser: $headerParser,
            events: $events,
            request: new HttpRequest('http://127.0.0.1:65535', 'GET', [], '', ['stream' => true]),
        );

        StreamingCurlExecFailureHook::$forceComplete = true;
        StreamingCurlExecFailureHook::$forceTransferFailure = true;
        try {
            expect(fn() => iterator_to_array($adapter->toHttpResponse()->stream()))
                ->toThrow(\Cognesy\Http\Exceptions\TimeoutException::class);
        } finally {
            StreamingCurlExecFailureHook::$forceComplete = false;
            StreamingCurlExecFailureHook::$forceTransferFailure = false;
        }

        $completed = array_values(array_filter(
            $captured,
            static fn(object $event): bool => $event instanceof HttpStreamCompleted,
        ));

        expect($completed)->toHaveCount(1)
            ->and($completed[0]->data['outcome'])->toBe('failed')
            ->and($completed[0]->data['error'])->toContain('cURL transfer failed');
    });

    /**
     * HttpResponseChunkReceived is the argument to dispatch(), so before the guard the
     * adapter built one event object per fragment whether or not anything consumed it
     * — 822 objects and 994 KB per 205 KB SSE response, two thirds of it
     * Uuid::uuid4()'s CSPRNG draw. The guard asks CanCheckListeners once per stream.
     *
     * It may only suppress construction on a positive "nobody listens"; the chunks
     * yielded and the completion counters must be identical either way.
     */
    function guardAdapterFor(\Psr\EventDispatcher\EventDispatcherInterface $events, string $body, int $chunkSize): StreamingCurlResponseAdapter {
        $handle = CurlHandle::create('http://127.0.0.1:65535', 'GET');
        $multi = curl_multi_init();
        curl_multi_add_handle($multi, $handle->native());

        $queue = new \SplQueue();
        $queue->enqueue($body);

        $headerParser = new HeaderParser();
        $headerParser->parse("HTTP/1.1 200 OK\r\n");
        $headerParser->parse("Content-Type: text/event-stream\r\n");

        return new StreamingCurlResponseAdapter(
            handle: $handle,
            multi: $multi,
            queue: $queue,
            headerParser: $headerParser,
            events: $events,
            requestId: 'req-guard-curl',
            chunkSize: $chunkSize,
        );
    }

    /** @return list<string> */
    function drainGuarded(StreamingCurlResponseAdapter $adapter): array {
        StreamingCurlExecFailureHook::$forceComplete = true;
        try {
            return iterator_to_array($adapter->toHttpResponse()->stream(), false);
        } finally {
            StreamingCurlExecFailureHook::$forceComplete = false;
        }
    }

    it('skips chunk event construction when nothing listens, without changing the chunks', function () {
        if (!extension_loaded('curl')) {
            Assert::markTestSkipped('cURL extension not available');
        }

        // Wrapping a listener-free dispatcher is the only way to see what reached
        // dispatch() at all: attaching a wiretap would make hasListenersFor() true and
        // the guard would correctly stop guarding.
        $events = new class(new EventDispatcher()) implements \Psr\EventDispatcher\EventDispatcherInterface, \Cognesy\Events\Contracts\CanCheckListeners {
            /** @var list<object> */
            public array $dispatched = [];

            public function __construct(private readonly EventDispatcher $inner) {}

            #[\Override]
            public function dispatch(object $event): object {
                $this->dispatched[] = $event;

                return $this->inner->dispatch($event);
            }

            #[\Override]
            public function hasListenersFor(string $eventClass): bool {
                return $this->inner->hasListenersFor($eventClass);
            }
        };

        expect($events->hasListenersFor(HttpResponseChunkReceived::class))->toBeFalse();

        $chunks = drainGuarded(guardAdapterFor($events, 'abcdefg', 3));

        expect($chunks)->toBe(['abc', 'def', 'g'])
            ->and(array_filter($events->dispatched, static fn(object $e): bool => $e instanceof HttpResponseChunkReceived))
            ->toBe([])
            // Only the per-chunk event is guarded; completion still fires.
            ->and(array_filter($events->dispatched, static fn(object $e): bool => $e instanceof HttpStreamCompleted))
            ->toHaveCount(1);
    });

    it('reports identical completion counters whether or not chunk events fire', function () {
        if (!extension_loaded('curl')) {
            Assert::markTestSkipped('cURL extension not available');
        }

        // A listener for HttpStreamCompleted only: hasListenersFor() must answer false
        // for the chunk class while completion still reaches its listener.
        $events = new EventDispatcher();
        $completed = null;
        $events->addListener(HttpStreamCompleted::class, static function (object $event) use (&$completed): void {
            $completed = $event;
        });

        expect($events->hasListenersFor(HttpResponseChunkReceived::class))->toBeFalse();

        $chunks = drainGuarded(guardAdapterFor($events, 'abcdefg', 3));

        expect($chunks)->toBe(['abc', 'def', 'g'])
            ->and($completed)->not->toBeNull()
            ->and($completed->data['chunks'])->toBe(3)
            ->and($completed->data['bytes'])->toBe(7);
    });
}
