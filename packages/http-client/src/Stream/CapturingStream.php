<?php declare(strict_types=1);

namespace Cognesy\Http\Stream;

use LogicException;
use Traversable;

final class CapturingStream implements StreamInterface
{
    private bool $started = false;
    private bool $completed = false;
    private int $bytes = 0;
    private int $chunks = 0;
    private int $capturedBytes = 0;
    private bool $truncated = false;
    private string $preview = '';
    /** @var list<string> */
    private array $capturedChunks = [];

    public function __construct(
        private readonly StreamInterface $inner,
        private readonly StreamCapturingPolicy $policy,
    ) {}

    #[\Override]
    public function getIterator(): Traversable {
        if ($this->started) {
            throw new LogicException('Captured stream is exhausted and cannot be replayed.');
        }
        $this->started = true;
        $consumedFully = false;

        try {
            foreach ($this->inner as $chunk) {
                $this->observe($chunk);
                yield $chunk;
            }
            $consumedFully = true;
        } finally {
            $this->completed = $consumedFully;
        }
    }

    #[\Override]
    public function isCompleted(): bool {
        return $this->completed;
    }

    public function stats(): StreamCaptureStats {
        return new StreamCaptureStats(
            bytes: $this->bytes,
            chunks: $this->chunks,
            capturedBytes: $this->capturedBytes,
            truncated: $this->truncated,
        );
    }

    public function preview(): string {
        return $this->preview;
    }

    public function capturedBody(): string {
        return match ($this->policy->mode) {
            StreamCaptureMode::Preview => $this->preview,
            StreamCaptureMode::Chunks, StreamCaptureMode::Full => implode('', $this->capturedChunks),
            StreamCaptureMode::None => '',
        };
    }

    /** @return list<string> */
    public function capturedChunks(): array {
        return $this->capturedChunks;
    }

    private function observe(string $chunk): void {
        $this->bytes += strlen($chunk);
        $this->chunks++;

        if (!$this->shouldCapture($chunk)) {
            return;
        }

        $captured = $this->capturablePrefix($chunk);
        if ($captured === '') {
            return;
        }

        match ($this->policy->mode) {
            StreamCaptureMode::Preview => $this->preview .= $captured,
            StreamCaptureMode::Chunks, StreamCaptureMode::Full => $this->capturedChunks[] = $captured,
            StreamCaptureMode::None => null,
        };
        $this->capturedBytes += strlen($captured);
    }

    private function shouldCapture(string $chunk): bool {
        if (!$this->policy->enabled || $this->policy->mode === StreamCaptureMode::None || $chunk === '') {
            return false;
        }

        if ($this->capturedBytes < $this->policy->maxBytes) {
            return true;
        }

        $this->truncated = true;
        return false;
    }

    private function capturablePrefix(string $chunk): string {
        $remainingBytes = $this->policy->maxBytes - $this->capturedBytes;
        $chunkBytes = strlen($chunk);

        if ($chunkBytes <= $remainingBytes) {
            return $chunk;
        }

        $this->truncated = true;
        return substr($chunk, 0, $remainingBytes);
    }
}
