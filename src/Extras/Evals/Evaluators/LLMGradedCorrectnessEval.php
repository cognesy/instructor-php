<?php

namespace Cognesy\Instructor\Extras\Evals\Evaluators;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Evals\Contracts\CanCritiqueExecution;
use Cognesy\Instructor\Extras\Evals\Contracts\CanMeasureExecution;
use Cognesy\Instructor\Extras\Evals\Contracts\Metric;
use Cognesy\Instructor\Extras\Evals\Evaluators\Data\GradedCorrectnessAnalysis;
use Cognesy\Instructor\Extras\Evals\Execution;
use Cognesy\Instructor\Extras\Evals\Feedback\Feedback;
use Cognesy\Instructor\Extras\Evals\Metrics\Correctness\GradedCorrectness;
use Cognesy\Instructor\Instructor;

class LLMGradedCorrectnessEval implements CanMeasureExecution, CanCritiqueExecution
{
    private GradedCorrectnessAnalysis $result;

    public function __construct(
        private string $name,
        private array $expected,
        private array $actual,
        private ?Instructor $instructor = null,
    ) {
        $this->instructor = $instructor ?? new Instructor();
    }

    public function critique(Execution $execution): Feedback {
        $response = $this->call();
        return new Feedback($response->feedback);
    }

    public function measure(Execution $execution): Metric {
        $response = $this->call();
        return new GradedCorrectness(
            name: $this->name,
            value: $response->correctness,
        );
    }

    private function call() : GradedCorrectnessAnalysis {
        if (!$this->result) {
            $this->result = $this->llmEval();
        }
        return $this->result;
    }

    private function llmEval() : GradedCorrectnessAnalysis {
        return $this->instructor->respond(
            input: [
                'expected_result' => $this->expected,
                'actual_result' => $this->actual
            ],
            responseModel: GradedCorrectnessAnalysis::class,
            prompt: 'Analyze the expected and actual results and determine how correct the actual result is.',
            toolName: 'correctness_grade',
            toolDescription: 'Respond with grade of correctness to indicate to what extent the actual result is correct.',
            mode: Mode::Json,
        )->get();
    }
}
