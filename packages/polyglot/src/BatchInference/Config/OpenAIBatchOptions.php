<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Config;

use InvalidArgumentException;

final readonly class OpenAIBatchOptions implements BatchSubmissionOptions
{
    public function __construct(private string $completionWindow = '24h')
    {
        if ($completionWindow !== '24h') {
            throw new InvalidArgumentException('OpenAI currently documents a 24h batch completion window.');
        }
    }

    #[\Override]
    public function provider(): string
    {
        return 'openai';
    }
    public function completionWindow(): string
    {
        return $this->completionWindow;
    }
}
