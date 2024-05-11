<?php

namespace Cognesy\Instructor\ApiClient;

use Cognesy\Instructor\Enums\Mode;

class ModelParams
{
    public function __construct(
        public string $label = 'Undefined',
        public string $type = 'undefined',
        public string $name = 'undefined',
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

    public function costFor(int $inputTokens, int $outputTokens) : float {
        return ($this->inputCost * $inputTokens + $this->outputCost * $outputTokens) / 1_000_000;
    }

    public function toArray() : array {
        return [
            'label' => $this->label,
            'type' => $this->type,
            'name' => $this->name,
            'maxTokens' => $this->maxTokens,
            'contextSize' => $this->contextSize,
            'inputCost' => $this->inputCost,
            'outputCost' => $this->outputCost,
            'stopTokens' => $this->stopTokens,
            'roleMap' => $this->roleMap,
            'modes' => $this->modes,
        ];
    }
}
