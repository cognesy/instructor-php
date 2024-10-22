<?php

namespace Cognesy\Instructor\Extras\Evals\Evaluators;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Evals\Contracts\CanCritiqueExecution;
use Cognesy\Instructor\Extras\Evals\Contracts\CanMeasureExecution;
use Cognesy\Instructor\Extras\Evals\Contracts\Metric;
use Cognesy\Instructor\Extras\Evals\Evaluators\Data\BooleanCorrectnessAnalysis;
use Cognesy\Instructor\Extras\Evals\Execution;
use Cognesy\Instructor\Extras\Evals\Feedback\Feedback;
use Cognesy\Instructor\Extras\Evals\Metrics\Correctness\BooleanCorrectness;
use Cognesy\Instructor\Instructor;

class LLMBooleanCorrectnessEval implements CanMeasureExecution, CanCritiqueExecution
{
    private BooleanCorrectnessAnalysis $result;

    public function __construct(
        private string $name,
        private array $expected,
        private array $actual,
        private ?Instructor $instructor = null,
    ) {
        $this->instructor = $instructor ?? new Instructor();
    }

//    public function evaluate(Execution $execution) : Evaluation {
//        /** @var BooleanCorrectnessAnalysis $result */
//        return new Evaluation(
//            metric: new BooleanCorrectness(
//                name: $this->name,
//                value: $result->isCorrect,
//            ),
//            feedback: new Feedback($result->feedback),
//            usage: $request->response()->usage(),
//        );
//    }

    public function critique(Execution $execution): Feedback {
        $response = $this->call();
        return new Feedback($response->feedback);
    }

    public function measure(Execution $execution): Metric {
        $response = $this->call();
        return new BooleanCorrectness(
            name: $this->name,
            value: $response->isCorrect,
        );
    }

    private function call() : BooleanCorrectnessAnalysis {
        if (!$this->result) {
            $this->result = $this->llmEval();
        }
        return $this->result;
    }

    private function llmEval() : BooleanCorrectnessAnalysis {
        return $this->instructor->request(
            input: [
                'expected_result' => $this->expected,
                'actual_result' => $this->actual
            ],
            responseModel: BooleanCorrectnessAnalysis::class,
            prompt: 'Analyze the expected and actual results and determine if the actual result is correct.',
            toolName: 'correctness_evaluation',
            toolDescription: 'Respond with true or false to indicate if the actual result is correct.',
            mode: Mode::Json,
        )->get();
    }
}
