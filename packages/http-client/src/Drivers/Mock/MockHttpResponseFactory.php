<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Mock;

use Cognesy\Http\Data\HttpResponse;

/**
 * MockHttpResponse
 *
 * A simple implementation of HttpResponse for testing purposes.
 */
class MockHttpResponseFactory
{
    /**
     * Static factory to create a successful response
     */
    public static function success(int $statusCode = 200, array $headers = [], string $body = '', array $chunks = []): HttpResponse {
        if (!empty($chunks)) {
            return HttpResponse::streaming(
                statusCode: $statusCode,
                headers: $headers,
                stream: new \Cognesy\Http\Stream\ArrayStream($chunks),
            );
        }
        return HttpResponse::sync(
            statusCode: $statusCode,
            headers: $headers,
            body: $body,
        );
    }

    /**
     * Static factory to create an error response
     */
    public static function error(int $statusCode = 500, array $headers = [], string $body = '', array $chunks = []): HttpResponse {
        if (!empty($chunks)) {
            return HttpResponse::streaming(
                statusCode: $statusCode,
                headers: $headers,
                stream: new \Cognesy\Http\Stream\ArrayStream($chunks),
            );
        }
        return HttpResponse::sync(
            statusCode: $statusCode,
            headers: $headers,
            body: $body,
        );
    }

    /**
     * Static factory to create a streaming response
     */
    public static function streaming(int $statusCode = 200, array $headers = [], array $chunks = []): HttpResponse {
        return HttpResponse::streaming(
            statusCode: $statusCode,
            headers: $headers,
            stream: new \Cognesy\Http\Stream\ArrayStream($chunks),
        );
    }

    /**
     * Convenience: return a JSON response.
     */
    public static function json(
        array|string|\JsonSerializable $data,
        int $statusCode = 200,
        array $headers = []
    ): HttpResponse {
        $body = is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            $body = '';
        }
        $headers = ['content-type' => 'application/json', ...$headers];
        return HttpResponse::sync(
            statusCode: $statusCode,
            headers: $headers,
            body: $body,
        );
    }

    /**
     * Convenience: return a Server-Sent Events (SSE) streaming response from JSON payloads.
     * Each element in $payloads is encoded as a line: "data: {json}\n\n". Optionally appends DONE.
     */
    public static function sse(
        array $payloads,
        bool $addDone = true,
        int $statusCode = 200,
        array $headers = []
    ): HttpResponse {
        $chunks = [];
        foreach ($payloads as $item) {
            $json = is_string($item) ? $item : json_encode($item, JSON_UNESCAPED_SLASHES);
            $chunks[] = "data: {$json}\n\n";
        }
        if ($addDone) {
            $chunks[] = "data: [DONE]\n\n";
        }
        $headers = ['content-type' => 'text/event-stream', ...$headers];
        return self::streaming($statusCode, $headers, $chunks);
    }
}
