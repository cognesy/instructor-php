<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Preparation;

use LogicException;

final class PreparedBatchInput
{
    private bool $closed = false;

    public function __construct(
        private readonly string $path,
        private readonly int $count,
        private readonly int $bytes,
    ) {
    }

    public function path(): string
    {
        if ($this->closed) {
            throw new LogicException('Prepared batch input has been closed.');
        }

        return $this->path;
    }

    public function count(): int
    {
        return $this->count;
    }
    public function bytes(): int
    {
        return $this->bytes;
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }
        $this->closed = true;
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
