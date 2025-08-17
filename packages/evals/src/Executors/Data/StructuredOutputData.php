<?php declare(strict_types=1);

namespace Cognesy\Evals\Executors\Data;

class StructuredOutputData
{
    public function __construct(
        public string|array $messages = '',
        public string|array|object $responseModel = '',
        public int $maxTokens = 512,
        public string $toolName = '',
        public string $toolDescription = '',
        public string $system = '',
        public string $prompt = '',
        public array $examples = [],
        public string $model = '',
        public string $retryPrompt = '',
        public int $maxRetries = 0,
        public float $temperature = 1.0,
    ) {}

    public function responseModel() : string|array|object {
        return $this->responseModel;
    }
}