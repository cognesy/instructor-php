<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Config;

use InvalidArgumentException;

final readonly class GroqBatchOptions implements BatchSubmissionOptions
{
    public function __construct(private string $completionWindow = '24h')
    {
        if (!preg_match('/^([1-9][0-9]*)(h|d)$/', $completionWindow, $matches)) {
            throw new InvalidArgumentException('Groq batch window must be an hour or day duration.');
        }
        $hours = (int) $matches[1] * ($matches[2] === 'd' ? 24 : 1);
        if ($hours < 24 || $hours > 168) {
            throw new InvalidArgumentException('Groq batch window must be between 24 hours and 7 days.');
        }
    }

    #[\Override]
    public function provider(): string
    {
        return 'groq';
    }
    public function completionWindow(): string
    {
        return $this->completionWindow;
    }
}
