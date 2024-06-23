<?php

namespace Cognesy\Instructor\Evaluation\Data;

use Cognesy\Instructor\Data\Example;

class PromptEvaluation
{
    public string $prompt = '';
    public array $examples = [];
    public mixed $input = '';
    private string|array|object $outputModel = '';

    public array $expectedResult = [];
    public array $actualResult = [];

    public EvaluationResult $result;

    public function __construct(
        string $prompt = '',
        array $examples = [],
        mixed $input = '',
        string|array|object $outputModel = [],
        array $expectedResult = [],
        array $actualResult = [],
    ) {
        $this->prompt = $prompt;
        $this->examples = $this->normalizeExamples($examples);
        $this->input = $input;
        $this->outputModel = $outputModel;
        $this->expectedResult = $expectedResult;
        $this->actualResult = $actualResult;
    }

    public function withResult(EvaluationResult $result) : static {
        $this->result = $result;
        return $this;
    }

    public function result() : EvaluationResult {
        return $this->result;
    }

    public function withPrompt(string $prompt) : static {
        $this->prompt = $prompt;
        return $this;
    }

    public function prompt() : string {
        return $this->prompt;
    }

    public function withExamples(array $examples) : static {
        $this->examples = $this->normalizeExamples($examples);
        return $this;
    }

    public function examples() : array {
        return $this->examples;
    }

    public function withInput(mixed $input) : static {
        $this->input = $input;
        return $this;
    }

    public function input() : mixed {
        return $this->input;
    }

    public function withOutputModel(array $outputModel) : static {
        $this->outputModel = $outputModel;
        return $this;
    }

    public function outputModel() : array {
        return $this->outputModel;
    }

    public function actualResult() : array {
        return $this->actualResult;
    }

    public function withActualResult(array $actualResult) : static {
        $this->actualResult = $actualResult;
        return $this;
    }

    public function expectedResult() : array {
        return $this->expectedResult;
    }

    public function withExpectedResult(array $expectedResult) : static {
        $this->expectedResult = $expectedResult;
        return $this;
    }

    /**
     * Prepare examples for evaluation
     * @var array<array> $examples
     * @return Example[]
     */
    public function normalizeExamples(array $examples) : array {
        $normalized = [];
        foreach ($examples as $example) {
            $normalized[] = new Example($example['input'], $example['output']);
        }
        return $normalized;
    }
}
