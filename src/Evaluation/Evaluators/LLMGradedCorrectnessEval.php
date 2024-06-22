<?php

namespace Cognesy\Instructor\Evaluation\Evaluators;

use Cognesy\Instructor\Evaluation\Contracts\CanEvaluate;
use Cognesy\Instructor\Evaluation\Enums\CorrectnessGrade;
use Cognesy\Instructor\Evaluation\Metrics\GradedCorrectness;
use Cognesy\Instructor\Extras\Scalar\Scalar;
use Cognesy\Instructor\Instructor;

class LLMGradedCorrectnessEval implements CanEvaluate
{
    public function __construct(
        private array $expected,
        private array $actual,
        private ?Instructor $instructor = null,
    ) {
        $this->instructor = $instructor ?? new Instructor();
    }

    public function evaluate() : GradedCorrectness {
        $result = $this->instructor->respond(
            input: [
                'expected_result' => $this->expected,
                'actual_result' => $this->actual
            ],
            responseModel: Scalar::enum(CorrectnessGrade::class),
            prompt: 'Analyze the expected and actual results and determine how correct the actual result is.',
            toolTitle: 'correctness_grade',
            toolDescription: 'Respond with grade of correctness to indicate to what extent the actual result is correct.'
        );
        return new GradedCorrectness($result);
    }
}
