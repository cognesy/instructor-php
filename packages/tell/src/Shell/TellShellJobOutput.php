<?php

declare(strict_types=1);

namespace Cognesy\Tell\Shell;

final readonly class TellShellJobOutput
{
    /** @param list<TellShellJobOutputChunk> $chunks */
    public function __construct(
        public string $jobId,
        public array $chunks,
        public int $nextCursor,
        public bool $truncated,
        public bool $hasMore,
    ) {}

    public function text(): string
    {
        return implode('', array_map(
            static fn (TellShellJobOutputChunk $chunk): string => $chunk->text,
            $this->chunks,
        ));
    }
}
