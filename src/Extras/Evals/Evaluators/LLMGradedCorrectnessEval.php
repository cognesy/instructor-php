<?php

namespace Cognesy\Instructor\Extras\Evals\Evaluators;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Evals\Contracts\CanEvaluateExperiment;
use Cognesy\Instructor\Extras\Evals\Data\Evaluation;
use Cognesy\Instructor\Extras\Evals\Data\Feedback;
use Cognesy\Instructor\Extras\Evals\Data\GradedCorrectnessAnalysis;
use Cognesy\Instructor\Extras\Evals\Experiment;
use Cognesy\Instructor\Extras\Evals\Metrics\GradedCorrectness;
use Cognesy\Instructor\Instructor;

class LLMGradedCorrectnessEval implements CanEvaluateExperiment
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
        /** @var GradedCorrectnessAnalysis $result */
        $request = $this->instructor->respond(
            input: [
                'expected_result' => $this->expected,
                'actual_result' => $this->actual
            ],
            responseModel: GradedCorrectnessAnalysis::class,
            prompt: 'Analyze the expected and actual results and determine how correct the actual result is.',
            toolName: 'correctness_grade',
            toolDescription: 'Respond with grade of correctness to indicate to what extent the actual result is correct.',
            mode: Mode::Json,
        );

        $result = $request->get();

        return new Evaluation(
            metric: new GradedCorrectness(
                grade: $result->correctness,
                name: $this->name,
            ),
            feedback: new Feedback($result->feedback),
            usage: $request->response()->usage(),
        );
    }
}
