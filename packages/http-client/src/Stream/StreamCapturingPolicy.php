<?php declare(strict_types=1);

namespace Cognesy\Http\Stream;

readonly final class StreamCapturingPolicy
{
    public bool $enabled;
    public StreamCaptureMode $mode;
    public int $maxBytes;

    public function __construct(
        bool $enabled = false,
        StreamCaptureMode $mode = StreamCaptureMode::None,
        int $maxBytes = 65536,
    ) {
        $this->enabled = $enabled;
        $this->mode = $enabled ? $mode : StreamCaptureMode::None;
        $this->maxBytes = max(0, $maxBytes);
    }

    public static function disabled(): self {
        return new self(enabled: false, mode: StreamCaptureMode::None, maxBytes: 0);
    }

    public static function preview(int $maxBytes = 65536): self {
        return new self(enabled: true, mode: StreamCaptureMode::Preview, maxBytes: $maxBytes);
    }

    public static function chunks(int $maxBytes = 1048576): self {
        return new self(enabled: true, mode: StreamCaptureMode::Chunks, maxBytes: $maxBytes);
    }

    public static function full(int $maxBytes): self {
        return new self(enabled: true, mode: StreamCaptureMode::Full, maxBytes: $maxBytes);
    }
}
