<?php

namespace Cognesy\Instructor\Evaluation\Evaluators;

use Cognesy\Instructor\Evaluation\Contracts\CanEvaluateResult;
use Cognesy\Instructor\Evaluation\Contracts\CanProvideFeedback;
use Cognesy\Instructor\Evaluation\Data\Feedback;
use Cognesy\Instructor\Evaluation\Metrics\BooleanCorrectness;
use Cognesy\Instructor\Evaluation\ResponseModels\BooleanCorrectnessAnalysis;
use Cognesy\Instructor\Instructor;

class LLMCoTBoolCorrectnessEval implements CanEvaluateResult, CanProvideFeedback
{
    private BooleanCorrectnessAnalysis $analysis;

    public function __construct(
        private array $expected,
        private array $actual,
        private ?Instructor $instructor = null,
    ) {
        $this->instructor = $instructor ?? new Instructor();
    }

    public function evaluate() : BooleanCorrectness {
        /** @var BooleanCorrectnessAnalysis $result */
        $result = $this->instructor->respond(
            input: [
                'expected_result' => $this->expected,
                'actual_result' => $this->actual
            ],
            responseModel: BooleanCorrectnessAnalysis::class,
            prompt: 'Analyze the expected and actual results and determine if the actual result is correct.',
            toolTitle: 'correctness_evaluation',
            toolDescription: 'Respond with true or false to indicate if the actual result is correct.'
        );
        $this->analysis = $result;
        return new BooleanCorrectness($result->isCorrect);
    }

    public function feedback(): Feedback {
        return $this->analysis->feedback;
    }
}
