<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Config;

use InvalidArgumentException;

final readonly class BatchReadRetryPolicy
{
    public function __construct(
        private int $maxAttempts = 3,
        private int $baseDelayMilliseconds = 200,
        private int $maxDelayMilliseconds = 2000,
    ) {
        if ($maxAttempts < 1 || $baseDelayMilliseconds < 0 || $maxDelayMilliseconds < $baseDelayMilliseconds) {
            throw new InvalidArgumentException('Invalid batch read retry policy.');
        }
    }

    public function maxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function delayAfter(int $attempt): int
    {
        if ($attempt < 1) {
            throw new InvalidArgumentException('Retry attempt must be positive.');
        }
        if ($this->baseDelayMilliseconds === 0) {
            return 0;
        }
        $delay = $this->baseDelayMilliseconds;
        for ($index = 1; $index < $attempt && $delay < $this->maxDelayMilliseconds; $index++) {
            $delay = $delay > intdiv($this->maxDelayMilliseconds, 2)
                ? $this->maxDelayMilliseconds
                : $delay * 2;
        }
        return $delay;
    }
}
