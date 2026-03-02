<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Curl {
    final class StreamingCurlExecFailureHook
    {
        public static bool $forceExecFailure = false;
    }

    function curl_multi_exec(\CurlMultiHandle $multiHandle, &$stillRunning): int {
        if (StreamingCurlExecFailureHook::$forceExecFailure) {
            $stillRunning = 1;
            return CURLM_BAD_HANDLE;
        }

        return \curl_multi_exec($multiHandle, $stillRunning);
    }
}

namespace {
    use Cognesy\Events\Dispatchers\EventDispatcher;
    use Cognesy\Http\Drivers\Curl\CurlHandle;
    use Cognesy\Http\Drivers\Curl\HeaderParser;
    use Cognesy\Http\Drivers\Curl\StreamingCurlExecFailureHook;
    use Cognesy\Http\Drivers\Curl\StreamingCurlResponseAdapter;
    use Cognesy\Http\Exceptions\NetworkException;

    it('throws when curl_multi_exec fails during streaming instead of ending silently', function () {
        if (!extension_loaded('curl')) {
            $this->markTestSkipped('cURL extension not available');
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
}
