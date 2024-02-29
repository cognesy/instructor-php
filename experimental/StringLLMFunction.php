<?php
namespace Cognesy\Experimental;

use Cognesy\Instructor\LLMs\OpenAI\LLM;
use Cognesy\Instructor\Reflection\Enums\PhpType;

class StringLLMFunction
{
    private LLM $llm;

    public function __construct(LLM $llm = null) {
        $this->llm = $llm ?? new LLM();
    }

    public function make(
        string $name,
        string $description,
        array $messages,
        string $model = 'gpt-4-0125-preview',
        array $options = []
    ) : ?string {
        $schema = (new SimpleFunctionCallSchema)->make(
            $name,
            $description,
            'value',
            'Derive correct value based on context',
            PhpType::STRING
        );
        $json = $this->llm->callFunction($messages, $name, $schema, $model, $options);
        $deserialized = json_decode($json, true);
        return $deserialized['value'] ?? null;
    }
}