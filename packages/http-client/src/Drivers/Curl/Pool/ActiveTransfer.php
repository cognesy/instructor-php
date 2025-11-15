<?php declare(strict_types=1);

namespace Cognesy\Http\Drivers\Curl\Pool;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Drivers\Curl\CurlHandle;
use Cognesy\Http\Drivers\Curl\HeaderParser;

/**
 * Value object representing a single active HTTP transfer in curl_multi
 *
 * Encapsulates all the state needed to track an ongoing transfer:
 * - The curl handle performing the transfer
 * - The header parser accumulating response headers
 * - The original HTTP request
 * - The index in the original request array (for response ordering)
 */
final readonly class ActiveTransfer
{
    public function __construct(
        public CurlHandle $handle,
        public HeaderParser $parser,
        public HttpRequest $request,
        public int $index,
    ) {}
}
