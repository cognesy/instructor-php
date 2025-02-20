<?php

namespace Cognesy\Addons\Evals\Observers\Evaluate;

use Cognesy\Addons\Evals\Contracts\CanGenerateObservations;
use Cognesy\Addons\Evals\Execution;
use Cognesy\Addons\Evals\Feedback\Feedback;
use Cognesy\Addons\Evals\Observation;
use Cognesy\Addons\Evals\Observers\Evaluate\Data\BooleanCorrectnessAnalysis;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Instructor;

class LLMBooleanCorrectnessEval implements CanGenerateObservations
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

    public function accepts(mixed $subject): bool {
        return $subject instanceof Execution;
    }

    /**
     * Compiles an array of observations for the given subject by measuring and critiquing it.
     *
     * @param mixed $subject The subject to be observed.
     * @return iterable<Observation> The set of observations gathered from measurement and critique.
     */
    public function observations(mixed $subject): iterable {
        yield $this->measure($subject);
        yield from $this->critique($subject);
    }

    // INTERNAL /////////////////////////////////////////////////

    private function critique(Execution $execution): array {
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

    private function measure(Execution $execution): Observation {
        $response = $this->call();
        return Observation::make(
            type: 'metric',
            key: $this->name,
            value: $response->isCorrect,
            metadata: [
                'executionId' => $execution->id(),
                'unit' => 'boolean',
            ],
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
