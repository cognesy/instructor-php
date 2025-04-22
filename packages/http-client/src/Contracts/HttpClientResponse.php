<?php

namespace Cognesy\Http\Contracts;

use Generator;

/**
 * Interface HttpClientResponse
 *
 * Defines the contract for an HTTP client response implemented by various HTTP clients
 */
interface HttpClientResponse
{
    /**
     * Get the response status code
     *
     * @return int
     */
    public function statusCode(): int;

    /**
     * Get the response headers
     *
     * @return array
     */
    public function headers(): array;

    /**
     * Get the response
     *
     * @return string
     */
    public function body(): string;

    /**
     * Read chunks of the stream
     *
     * @param int $chunkSize
     * @return Generator<string>
     */
    public function stream(int $chunkSize = 1): Generator;
}
