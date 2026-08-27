<?php

declare(strict_types=1);

namespace Cognesy\Tell\Resource;

use Cognesy\Tell\Shell\TellShellJobOutput;
use Cognesy\Tell\Shell\TellShellJobOutputChunk;

/** @internal */
final class TellShellJobOutputBuffer
{
    /** @var list<array{start: int, end: int, stream: string, text: string}> */
    private array $records = [];

    private int $cursor = 0;

    private int $evictedThrough = 0;

    private int $retainedBytes = 0;

    private int $stdoutBytes = 0;

    private int $stderrBytes = 0;

    private bool $truncated = false;

    public function __construct(private readonly int $maxRetainedBytes) {}

    public function append(string $stream, string $text): void
    {
        if ($text === '') {
            return;
        }
        $bytes = strlen($text);
        $start = $this->cursor;
        $this->cursor += $bytes;
        $this->records[] = [
            'start' => $start,
            'end' => $this->cursor,
            'stream' => $stream,
            'text' => $text,
        ];
        $this->retainedBytes += $bytes;
        if ($stream === 'stdout') {
            $this->stdoutBytes += $bytes;
        } else {
            $this->stderrBytes += $bytes;
        }
        $this->enforceBound();
    }

    public function read(string $jobId, int $after, int $maxBytes): TellShellJobOutput
    {
        $requested = max(0, $after);
        $cursor = max($requested, $this->evictedThrough);
        $remaining = $maxBytes;
        $chunks = [];
        foreach ($this->records as $record) {
            if ($record['end'] <= $cursor) {
                continue;
            }
            $offset = max(0, $cursor - $record['start']);
            $available = strlen($record['text']) - $offset;
            $length = min($remaining, $available);
            if ($length < 1) {
                break;
            }
            $text = substr($record['text'], $offset, $length);
            $cursor += strlen($text);
            $remaining -= strlen($text);
            $chunks[] = new TellShellJobOutputChunk($cursor, $record['stream'], $text);
            if ($remaining < 1) {
                break;
            }
        }

        return new TellShellJobOutput(
            jobId: $jobId,
            chunks: $chunks,
            nextCursor: $cursor,
            truncated: $requested < $this->evictedThrough,
            hasMore: $cursor < $this->cursor,
        );
    }

    public function stdoutBytes(): int
    {
        return $this->stdoutBytes;
    }

    public function stderrBytes(): int
    {
        return $this->stderrBytes;
    }

    public function wasTruncated(): bool
    {
        return $this->truncated;
    }

    private function enforceBound(): void
    {
        while ($this->retainedBytes > $this->maxRetainedBytes && $this->records !== []) {
            $excess = $this->retainedBytes - $this->maxRetainedBytes;
            $first = $this->records[0];
            $bytes = strlen($first['text']);
            if ($bytes <= $excess) {
                array_shift($this->records);
                $this->retainedBytes -= $bytes;
                $this->evictedThrough = $first['end'];
                $this->truncated = true;

                continue;
            }

            $first['text'] = substr($first['text'], $excess);
            $first['start'] += $excess;
            $this->records[0] = $first;
            $this->retainedBytes -= $excess;
            $this->evictedThrough = $first['start'];
            $this->truncated = true;
        }
    }
}
