<?php

namespace Cognesy\Evals\LLMModes;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Evals\Contracts\CanGenerateObservations;
use Cognesy\Instructor\Extras\Evals\Execution;
use Cognesy\Instructor\Extras\Evals\Observation;
use Cognesy\Instructor\Utils\Str;

class CompanyEval implements CanGenerateObservations
{
    private string $key;
    private array $expectations;

    public function __construct(
        string $key,
        array $expectations
    ) {
        $this->key = $key;
        $this->expectations = $expectations;
    }

    public function accepts(mixed $subject): bool {
        return $subject instanceof Execution;
    }

    public function observations(mixed $subject): iterable {
        yield $this->correctness($subject);
    }

    // INTERNAL /////////////////////////////////////////////////

    public function correctness(Execution $execution): Observation {
        $mode = $execution->get('case.mode');
        $isCorrect = match ($mode) {
            Mode::Text => $this->validateText($execution),
            Mode::Tools => $this->validateToolsData($execution),
            default => $this->validateDefault($execution),
        };
        return Observation::make(
            type: 'metric',
            key: $this->key,
            value: $isCorrect ? 1 : 0,
            metadata: [
                'executionId' => $execution->id(),
                'unit' => 'boolean',
            ],
        );
    }

    private function validateToolsData(Execution $execution) : bool {
        $data = $execution->get('response')->toolsData[0] ?? [];
        return 'store_company' === ($data['name'] ?? '')
            && 'ACME' === ($data['arguments']['name'] ?? '')
            && 2020 === (int) ($data['arguments']['year'] ?? 0);
    }

    private function validateDefault(Execution $execution) : bool {
        $decoded = $execution->get('response')?->json()->toArray();
        return $this->expectations['name'] === ($decoded['name'] ?? '')
            && $this->expectations['year'] === ($decoded['year'] ?? 0);
    }

    private function validateText(Execution $execution) : bool {
        $content = $execution->get('response')?->content();
        return Str::contains(
            $content,
            [
                $this->expectations['name'],
                (string) $this->expectations['year']
            ]
        );
    }
}
