<?php declare(strict_types=1);

namespace Cognesy\Http\Stream;

readonly final class StreamCaptureStats
{
    public function __construct(
        public int $bytes,
        public int $chunks,
        public int $capturedBytes,
        public bool $truncated,
    ) {}
}
