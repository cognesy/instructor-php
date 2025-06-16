<?php

namespace Cognesy\Http\Contracts;

use Generator;

interface HttpResponse
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
    public function stream(?int $chunkSize = null): iterable;
}
