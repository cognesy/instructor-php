<?php

namespace Cognesy\Addons\Evals\Observers\Evaluate;

use Cognesy\Addons\Evals\Contracts\CanGenerateObservations;
use Cognesy\Addons\Evals\Execution;
use Cognesy\Addons\Evals\Feedback\Feedback;
use Cognesy\Addons\Evals\Observation;
use Cognesy\Addons\Evals\Observers\Evaluate\Data\GradedCorrectnessAnalysis;
use Cognesy\Instructor\Instructor;
use Cognesy\LLM\LLM\Enums\Mode;

class LLMGradedCorrectnessEval implements CanGenerateObservations
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

    public function accepts(mixed $subject): bool {
        return $subject instanceof Execution;
    }

    public function observations(mixed $subject): iterable {
        yield $this->measure($subject);
        yield from $this->critique($subject);
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
