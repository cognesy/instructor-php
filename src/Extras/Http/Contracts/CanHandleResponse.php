<?php

namespace Cognesy\Instructor\Extras\Http\Contracts;

use Generator;

interface CanHandleResponse
{
    /**
     * Get the response
     *
     * @return int
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
