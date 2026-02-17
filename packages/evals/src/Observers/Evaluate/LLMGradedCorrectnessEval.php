<?php declare(strict_types=1);

namespace Cognesy\Evals\Observers\Evaluate;

use Cognesy\Evals\Contracts\CanGenerateObservations;
use Cognesy\Evals\Execution;
use Cognesy\Evals\Feedback\Feedback;
use Cognesy\Evals\Observation;
use Cognesy\Evals\Observers\Evaluate\Data\GradedCorrectnessAnalysis;
use Cognesy\Instructor\Contracts\CanCreateStructuredOutput;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Messages\Messages;

/**
 * @implements CanGenerateObservations<Execution>
 */
class LLMGradedCorrectnessEval implements CanGenerateObservations
{
    private ?GradedCorrectnessAnalysis $result = null;

    public function __construct(
        private string $name,
        private array $expected,
        private array $actual,
        private CanCreateStructuredOutput $structuredOutput,
    ) {}

    #[\Override]
    public function accepts(mixed $subject): bool {
        return $subject instanceof Execution;
    }

    #[\Override]
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
        if ($this->result === null) {
            $this->result = $this->llmEval();
        }
        return $this->result;
    }

    private function llmEval() : GradedCorrectnessAnalysis {
        $request = new StructuredOutputRequest(
            messages: Messages::fromInput([
                'expected_result' => $this->expected,
                'actual_result' => $this->actual,
            ]),
            requestedSchema: GradedCorrectnessAnalysis::class,
            prompt: 'Analyze the expected and actual results and determine how correct the actual result is.',
        );
        return $this->structuredOutput->create($request)->get();
    }
}
