<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Config;

use Cognesy\Polyglot\BatchInference\Enums\MistralBatchInputMode;
use InvalidArgumentException;

final readonly class MistralBatchOptions implements BatchSubmissionOptions
{
    public function __construct(
        private MistralBatchInputMode $inputMode = MistralBatchInputMode::File,
        private int $timeoutHours = 24,
    ) {
        if ($timeoutHours < 1) {
            throw new InvalidArgumentException('Mistral batch timeout must be positive.');
        }
    }

    #[\Override]
    public function provider(): string
    {
        return 'mistral';
    }
    public function inputMode(): MistralBatchInputMode
    {
        return $this->inputMode;
    }
    public function timeoutHours(): int
    {
        return $this->timeoutHours;
    }
}
