<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Config;

use InvalidArgumentException;

final readonly class BedrockBatchOptions implements BatchSubmissionOptions
{
    public function __construct(private int $timeoutHours = 24)
    {
        if ($timeoutHours < 24 || $timeoutHours > 168) {
            throw new InvalidArgumentException('Bedrock batch timeout must be between 24 and 168 hours.');
        }
    }

    #[\Override]
    public function provider(): string
    {
        return 'bedrock';
    }
    public function timeoutHours(): int
    {
        return $this->timeoutHours;
    }
}
