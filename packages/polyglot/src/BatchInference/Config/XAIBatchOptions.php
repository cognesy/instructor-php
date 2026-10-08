<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Config;

use InvalidArgumentException;

final readonly class XAIBatchOptions implements BatchSubmissionOptions
{
    public function __construct(private string $name = 'polyglot-batch')
    {
        if ($name === '') {
            throw new InvalidArgumentException('xAI batch name cannot be empty.');
        }
    }

    #[\Override]
    public function provider(): string
    {
        return 'xai';
    }
    public function name(): string
    {
        return $this->name;
    }
}
