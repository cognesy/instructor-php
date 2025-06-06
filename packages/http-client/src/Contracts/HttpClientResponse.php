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
    public function statusCode(): int;
    public function headers(): array;
    public function body(): string;
    public function isStreamed(): bool;

    /**
     * Read chunks of the stream
     *
     * @param int $chunkSize
     * @return Generator<string>
     */
    public function stream(int $chunkSize = 1): Generator;
}
