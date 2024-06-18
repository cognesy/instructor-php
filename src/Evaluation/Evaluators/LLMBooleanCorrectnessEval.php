<?php

namespace Cognesy\Instructor\Evaluation\Evaluators;

use Cognesy\Instructor\Evaluation\Contracts\CanEvaluateResult;
use Cognesy\Instructor\Evaluation\Metrics\BooleanCorrectness;
use Cognesy\Instructor\Extras\Scalar\Scalar;
use Cognesy\Instructor\Instructor;

class LLMBooleanCorrectnessEval implements CanEvaluateResult
{
    public function __construct(
        private array $expected,
        private array $actual,
        private ?Instructor $instructor = null,
    ) {
        $this->instructor = $instructor ?? new Instructor();
    }

    public function evaluate() : BooleanCorrectness {
        $result = $this->instructor->respond(
            input: [
                'expected_result' => $this->expected,
                'actual_result' => $this->actual
            ],
            responseModel: Scalar::boolean(),
            prompt: 'Analyze the expected and actual results and determine if the actual result is correct.',
            toolTitle: 'correctness_evaluation',
            toolDescription: 'Respond with true or false to indicate if the actual result is correct.'
        );
        return new BooleanCorrectness($result);
    }
}
