<?php

declare(strict_types=1);

namespace Cognesy\Tell\Data;

/** One decoded request entering the Tell agent protocol. */
final readonly class TellProtocolRequest
{
    public function __construct(
        public string $id,
        public TellRequest $request,
    ) {}
}
