<?php

namespace Cognesy\Instructor\Extras\Evals\Evaluators;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Evals\Contracts\CanProvideExecutionObservations;
use Cognesy\Instructor\Extras\Evals\Evaluators\Data\GradedCorrectnessAnalysis;
use Cognesy\Instructor\Extras\Evals\Execution;
use Cognesy\Instructor\Extras\Evals\Feedback\Feedback;
use Cognesy\Instructor\Extras\Evals\Observation;
use Cognesy\Instructor\Instructor;

class LLMGradedCorrectnessEval implements CanProvideExecutionObservations
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

    public function observations(Execution $subject): iterable {
        return array_filter([
            $this->measure($subject),
            ...$this->critique($subject),
        ]);
    }

    // INTERNAL /////////////////////////////////////////////////

    public function critique(Execution $execution): array {
        $response = $this->call();
        $feedback = new Feedback($response->feedback);
        $observations = [];
        foreach ($feedback->items() as $item) {
            $observations[] = $item->toObservation([
                'executionId' => $execution->id(),
            ]);
        }
        return $observations;
    }

    public function measure(Execution $execution): Observation {
        $response = $this->call();
        return Observation::make(
            type: 'metric',
            key: $this->name,
            value: $response->correctness->toFloat(),
            metadata: [
                'executionId' => $execution->id(),
                'grade' => $response->correctness->value,
            ],
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
