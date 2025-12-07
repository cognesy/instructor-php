<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Curl\Pool;

use Cognesy\Http\Contracts\CanAdaptHttpResponse;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Drivers\Curl\CurlHandle;
use Cognesy\Http\Drivers\Curl\HeaderParser;

/**
 * PoolCurlResponse - Response Adapter for Pooled Requests
 *
 * Specialized adapter for curl_multi pooled requests where the body
 * must be retrieved via curl_multi_getcontent (not curl_exec).
 *
 * Memory: Body is passed as parameter (unavoidable with curl_multi)
 * Lifecycle: Body retrieved externally via curl_multi_getcontent
 *
 * Why separate from SyncCurlResponse?
 * - curl_exec() doesn't work after curl_multi_exec() has driven the transfer
 * - Must use curl_multi_getcontent() instead
 * - Body must be retrieved before creating the response object
 */
final class PoolCurlResponseAdapter implements CanAdaptHttpResponse
{
    public function __construct(
        private readonly CurlHandle $handle,
        private readonly HeaderParser $headerParser,
    ) {}

    #[\Override]
    public function toHttpResponse() : HttpResponse {
        // For curl_multi, we MUST get the body via curl_multi_getcontent
        // This is different from sync requests where curl_exec returns it
        $body = curl_multi_getcontent($this->handle->native());
        if (!is_string($body)) {
            $body = '';
        }

        return HttpResponse::sync(
            statusCode: $this->handle->statusCode(),
            headers: $this->headerParser->headers(),
            body: $body,
        );
    }
}
