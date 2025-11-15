<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Curl;

use Cognesy\Http\Contracts\CanAdaptHttpResponse;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Exceptions\NetworkException;

/**
 * SyncCurlResponse - Synchronous Response Adapter
 *
 * Adapter for blocking curl requests. Executes curl_exec internally
 * and stores the result.
 *
 * Memory: Body stored once (unavoidable from curl_exec)
 * Lifecycle: Executes on construction, stores body internally
 */
final class SyncCurlResponseAdapter implements CanAdaptHttpResponse
{
    public function __construct(
        private readonly CurlHandle $handle,
        private readonly HeaderParser $headerParser,
    ) {}

    #[\Override]
    public function toHttpResponse() : HttpResponse {
        $body = $this->execute();
        return HttpResponse::sync(
            statusCode: $this->handle->statusCode(),
            headers: $this->headerParser->headers(),
            body: $body,
        );
    }

    // INTERNAL //////////////////////////////////////////////////////////////////////////

    private function execute(): string {
        $body = curl_exec($this->handle->native());
        if ($body === false) {
            throw new NetworkException('Curl error: ' . curl_error($this->handle->native()));
        }
        // curl_exec returns true when CURLOPT_RETURNTRANSFER is not set
        if ($body === true) {
            return '';
        }
        return $body;
    }
}
