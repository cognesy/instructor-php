<?php

namespace Cognesy\Instructor\Extras\Http\Contracts;

use Generator;

interface CanAccessResponse
{
    public function getStatusCode(): int;

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
