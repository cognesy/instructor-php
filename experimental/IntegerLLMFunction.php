<?php
namespace Cognesy\Experimental;

use Cognesy\Instructor\LLMs\OpenAI\LLM;
use Cognesy\Instructor\Reflection\Enums\PhpType;

class IntegerLLMFunction
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
    ) : ?int {
        $schema = (new SimpleFunctionCallSchema)->make(
            $name,
            $description,
            'value',
            'Derive correct value based on context',
            PhpType::INTEGER
        );
        $json = $this->llm->callFunction($messages, $name, $schema, $model, $options);
        dump($json);
        $deserialized = json_decode($json, true);
        return $deserialized['value'] ?? null;
    }
}
