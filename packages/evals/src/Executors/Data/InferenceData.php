<?php declare(strict_types=1);

namespace Cognesy\Evals\Executors\Data;

class InferenceData
{
    public function __construct(
        public string|array $messages = '',
        public string|array|object $schema = [],
        public int $maxTokens = 512,
        public string $toolName = '',
        public string $toolDescription = '',
    ) {}

    public function inferenceSchema() : InferenceSchema {
        // TODO: make it accept any and use SchemaFactory to generate EvalSchema object
        if (!$this->schema instanceof InferenceSchema) {
            throw new \Exception('Schema is not an instance of EvalSchema.');
        }
        return $this->schema;
    }
}