<?php declare(strict_types=1);

namespace Evals\LLMModes;

use Cognesy\Evals\Contracts\CanGenerateObservations;
use Cognesy\Evals\Execution;
use Cognesy\Evals\Observation;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Utils\Str;

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
            OutputMode::Text => $this->validateText($execution),
            OutputMode::Tools => $this->validateToolsData($execution),
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
        /** @var ToolCall $toolCall */
        $toolCall = $execution->get('response')->toolCalls()?->first();
        if (null === $toolCall) {
            return false;
        }
        return 'store_company' === $toolCall->name()
            && 'ACME' === $toolCall->stringValue('company_name')
            && 2020 === $toolCall->intValue('founding_year');
    }

    private function validateDefault(Execution $execution) : bool {
        $decoded = $execution->get('response')?->findJsonData()->toArray();
        return $this->expectations['company_name'] === ($decoded['company_name'] ?? '')
            && $this->expectations['founding_year'] === ($decoded['founding_year'] ?? 0);
    }

    private function validateText(Execution $execution) : bool {
        $content = $execution->get('response')?->content();
        return Str::containsAll(
            $content,
            [
                $this->expectations['company_name'],
                (string) $this->expectations['founding_year']
            ]
        );
    }

    private function meetsExpectations(array $data): bool {
        foreach ($this->expectations as $key => $value) {
            if (!isset($data[$key])) {
                return false;
            }
            if ($data[$key] !== $value) {
                return false;
            }
        }
        return true;
    }
}
