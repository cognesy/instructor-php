<?php

namespace Cognesy\Instructor\ApiClient;

use Cognesy\Instructor\Enums\Mode;

class ModelParams
{
    public function __construct(
        public string $label = 'Default',
        public string $type = 'default',
        public string $name = 'default',
        public int $maxTokens = 4096,
        public int $contextSize = 4096,
        public int $inputCost = 0,
        public int $outputCost = 0,
        public array $stopTokens = [],
        public array $roleMap = [
            'user' => 'user',
            'assistant' => 'assistant',
            'system' => 'system'
        ],
        public array $modes = [
            Mode::Tools,
            Mode::Json,
            Mode::MdJson
        ],
    ) {}

    public function cost(int $inputTokens, int $outputTokens) : float {
        return ($this->inputCost * $inputTokens + $this->outputCost * $outputTokens) / 1_000_000;
    }
}
