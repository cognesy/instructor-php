<?php

namespace Cognesy\Instructor\Extras\Evals\Evaluators;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Evals\Contracts\CanEvaluateExperiment;
use Cognesy\Instructor\Extras\Evals\Data\BooleanCorrectnessAnalysis;
use Cognesy\Instructor\Extras\Evals\Data\Evaluation;
use Cognesy\Instructor\Extras\Evals\Data\Feedback;
use Cognesy\Instructor\Extras\Evals\Experiment;
use Cognesy\Instructor\Extras\Evals\Metrics\BooleanCorrectness;
use Cognesy\Instructor\Instructor;

class LLMBooleanCorrectnessEval implements CanEvaluateExperiment
{
    public function __construct(
        private string $name,
        private array $expected,
        private array $actual,
        private ?Instructor $instructor = null,
    ) {
        $this->instructor = $instructor ?? new Instructor();
    }

    public function evaluate(Experiment $experiment) : Evaluation {
        /** @var BooleanCorrectnessAnalysis $result */
        $request = $this->instructor->request(
            input: [
                'expected_result' => $this->expected,
                'actual_result' => $this->actual
            ],
            responseModel: BooleanCorrectnessAnalysis::class,
            prompt: 'Analyze the expected and actual results and determine if the actual result is correct.',
            toolName: 'correctness_evaluation',
            toolDescription: 'Respond with true or false to indicate if the actual result is correct.',
            mode: Mode::Json,
        );
        $result = $request->get();

        return new Evaluation(
            metric: new BooleanCorrectness(
                value: $result->isCorrect,
                name: $this->name
            ),
            feedback: new Feedback($result->feedback),
            usage: $request->response()->usage(),
        );
    }
}
