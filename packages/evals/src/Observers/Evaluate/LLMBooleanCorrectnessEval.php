<?php declare(strict_types=1);

namespace Cognesy\Evals\Observers\Evaluate;

use Cognesy\Evals\Contracts\CanGenerateObservations;
use Cognesy\Evals\Execution;
use Cognesy\Evals\Feedback\Feedback;
use Cognesy\Evals\Observation;
use Cognesy\Evals\Observers\Evaluate\Data\BooleanCorrectnessAnalysis;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

/**
 * @implements CanGenerateObservations<Execution>
 */
class LLMBooleanCorrectnessEval implements CanGenerateObservations
{
    private ?BooleanCorrectnessAnalysis $result = null;

    public function __construct(
        private string $name,
        private array $expected,
        private array $actual,
        private ?StructuredOutput $structuredOutput = null,
    ) {
        $this->structuredOutput = $structuredOutput ?? new StructuredOutput();
    }

    #[\Override]
    public function accepts(mixed $subject): bool {
        return $subject instanceof Execution;
    }

    /**
     * Compiles an array of observations for the given subject by measuring and critiquing it.
     *
     * @param mixed $subject The subject to be observed.
     * @return iterable<Observation> The set of observations gathered from measurement and critique.
     */
    #[\Override]
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
        if ($this->result === null) {
            $this->result = $this->llmEval();
        }
        return $this->result;
    }

    private function llmEval() : BooleanCorrectnessAnalysis {
        return $this->structuredOutput
            ->withInput([
                'expected_result' => $this->expected,
                'actual_result' => $this->actual
            ])
            ->withResponseClass(BooleanCorrectnessAnalysis::class)
            ->withPrompt('Analyze the expected and actual results and determine if the actual result is correct.')
            ->withSystem('You are a correctness evaluator. You will be given an expected result and an actual result. Your task is to determine if the actual result is correct. Follow schema: <|json_schema|>')
            ->withToolName('correctness_evaluation')
            ->withToolDescription('Respond with true or false to indicate if the actual result is correct.')
            ->withOutputMode(OutputMode::Json)
            ->get();
    }
}
