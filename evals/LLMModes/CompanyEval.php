<?php

namespace Cognesy\Evals\LLMModes;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Evals\Contracts\CanEvaluateExecution;
use Cognesy\Instructor\Extras\Evals\Data\Evaluation;
use Cognesy\Instructor\Extras\Evals\Data\Feedback;
use Cognesy\Instructor\Extras\Evals\Execution;
use Cognesy\Instructor\Extras\Evals\Metrics\Correctness\BooleanCorrectness;
use Cognesy\Instructor\Features\LLM\Data\Usage;
use Cognesy\Instructor\Utils\Str;

class CompanyEval implements CanEvaluateExecution
{
    private array $expectations;

    public function __construct(array $expectations) {
        $this->expectations = $expectations;
    }

    public function evaluate(Execution $execution) : Evaluation {
        $mode = $execution->get('case.mode');
        $isCorrect = match ($mode) {
            Mode::Text => $this->validateText($execution),
            Mode::Tools => $this->validateToolsData($execution),
            default => $this->validateDefault($execution),
        };
        return new Evaluation(
            metric: new BooleanCorrectness(name: 'is_correct', value: $isCorrect),
            feedback: Feedback::none(),
            usage: Usage::none(),
        );
    }

    // INTERNAL /////////////////////////////////////////////////

    private function validateToolsData(Execution $execution) : bool {
        $data = $execution->get('response')->toolsData[0];
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
