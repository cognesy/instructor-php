<?php

namespace Cognesy\Instructor\Extras\Evals\Data;

class InferenceConfig
{
    public function __construct(
        public string|array $messages = '',
        public string|array|object $schema = [],
        public int $maxTokens = 512,
        public string $toolName = '',
        public string $toolDescription = '',
    ) {}
}