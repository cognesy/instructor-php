<?php declare(strict_types=1);

namespace Cognesy\Http\Extras\Support\RecordReplay;

use Cognesy\Http\Stream\StreamInterface;
use Closure;
use Generator;
use LogicException;
use RuntimeException;

/**
 * Lazily tees a one-shot stream into an owner-only temporary spool.
 *
 * Chunks are yielded before recording finalization. The spool avoids retaining
 * the complete response in PHP memory while the caller is consuming it.
 */
final class RecordingStream implements StreamInterface
{
    private bool $started = false;
    private bool $completed = false;
    /** @var Closure(iterable<string>): void */
    private readonly Closure $onCompleted;
    /** @var Closure(): void */
    private readonly Closure $onAbandoned;

    /**
     * @param callable(iterable<string>): void $onCompleted
     * @param callable(): void|null $onAbandoned
     */
    public function __construct(
        private readonly StreamInterface $source,
        callable $onCompleted,
        ?callable $onAbandoned = null,
    ) {
        $this->onCompleted = Closure::fromCallable($onCompleted);
        $this->onAbandoned = $onAbandoned === null
            ? static function (): void {}
            : Closure::fromCallable($onAbandoned);
    }

    #[\Override]
    public function getIterator(): \Traversable {
        if ($this->started) {
            throw new LogicException('Recording stream is exhausted and cannot be replayed.');
        }
        $this->started = true;

        $spool = tmpfile();
        if ($spool === false) {
            throw new RuntimeException('Failed to create temporary HTTP recording spool.');
        }

        $consumedFully = false;
        try {
            foreach ($this->source as $chunk) {
                $encoded = base64_encode($chunk) . "\n";
                if (fwrite($spool, $encoded) !== strlen($encoded)) {
                    throw new RuntimeException('Failed to write HTTP recording spool.');
                }
                yield $chunk;
            }

            fflush($spool);
            ($this->onCompleted)($this->chunksFromSpool($spool));
            $consumedFully = true;
        } finally {
            $this->completed = $consumedFully;
            if (!$consumedFully) {
                ($this->onAbandoned)();
            }
            fclose($spool);
        }
    }

    #[\Override]
    public function isCompleted(): bool {
        return $this->completed && $this->source->isCompleted();
    }

    /** @param resource $spool @return Generator<string> */
    private function chunksFromSpool(mixed $spool): Generator {
        rewind($spool);
        while (($line = fgets($spool)) !== false) {
            $chunk = base64_decode(rtrim($line, "\r\n"), true);
            if ($chunk === false) {
                throw new RuntimeException('Failed to read HTTP recording spool.');
            }
            yield $chunk;
        }
    }
}
