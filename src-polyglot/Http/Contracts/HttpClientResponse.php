<?php

namespace Cognesy\Polyglot\Http\Contracts;

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
    public function getStatusCode(): int;

    /**
     * Get the response headers
     *
     * @return array
     */
    public function getHeaders(): array;

    /**
     * Get the response
     *
     * @return string
     */
    public function getContents(): string;

    /**
     * Read chunks of the stream
     *
     * @param int $chunkSize
     * @return Generator<string>
     */
    public function streamContents(int $chunkSize = 1): Generator;
}
