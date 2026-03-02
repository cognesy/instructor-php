<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Curl {
    final class StreamingCurlHeaderTimeoutHook
    {
        public static bool $forceTimeout = false;
        private static bool $firstCall = true;

        public static function reset() : void {
            self::$firstCall = true;
        }

        public static function nextTime() : float {
            if (self::$firstCall) {
                self::$firstCall = false;
                return 0.0;
            }

            return 10.0;
        }
    }

    function microtime(bool $as_float = false) : float|string {
        if (!StreamingCurlHeaderTimeoutHook::$forceTimeout) {
            return \microtime($as_float);
        }

        return StreamingCurlHeaderTimeoutHook::nextTime();
    }
}

namespace {
    use Cognesy\Events\Dispatchers\EventDispatcher;
    use Cognesy\Http\Drivers\Curl\CurlHandle;
    use Cognesy\Http\Drivers\Curl\HeaderParser;
    use Cognesy\Http\Drivers\Curl\StreamingCurlHeaderTimeoutHook;
    use Cognesy\Http\Drivers\Curl\StreamingCurlResponseAdapter;
    use Cognesy\Http\Exceptions\TimeoutException;

    it('throws when headers are not received before streaming priming timeout', function () {
        if (!extension_loaded('curl')) {
            $this->markTestSkipped('cURL extension not available');
        }

        $handle = CurlHandle::create('http://127.0.0.1:65535', 'GET');
        $multi = curl_multi_init();
        curl_multi_add_handle($multi, $handle->native());

        $adapter = new StreamingCurlResponseAdapter(
            handle: $handle,
            multi: $multi,
            queue: new \SplQueue(),
            headerParser: new HeaderParser(),
            events: new EventDispatcher(),
            chunkSize: 64,
        );

        StreamingCurlHeaderTimeoutHook::$forceTimeout = true;
        StreamingCurlHeaderTimeoutHook::reset();

        try {
            expect(fn() => $adapter->statusCode())
                ->toThrow(TimeoutException::class, 'Timed out waiting for response headers');
        } finally {
            StreamingCurlHeaderTimeoutHook::$forceTimeout = false;
            StreamingCurlHeaderTimeoutHook::reset();
        }
    });
}
