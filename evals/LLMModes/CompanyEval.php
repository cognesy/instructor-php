<?php

namespace Cognesy\Evals\LLMModes;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Evals\Contracts\CanEvaluateExperiment;
use Cognesy\Instructor\Extras\Evals\Experiment;
use Cognesy\Instructor\Extras\Evals\Metrics\BooleanCorrectness;
use Cognesy\Instructor\Extras\Evals\Contracts\Metric;
use Cognesy\Instructor\Utils\Str;

class CompanyEval implements CanEvaluateExperiment
{
    private array $expectations;

    public function __construct(array $expectations) {
        $this->expectations = $expectations;
    }

    public function evaluate(Experiment $experiment) : Metric {
        $isCorrect = match ($experiment->mode) {
            Mode::Text => $this->validateText($experiment),
            Mode::Tools => $this->validateToolsData($experiment),
            default => $this->validateDefault($experiment),
        };
        return new BooleanCorrectness($isCorrect);
    }

    private function validateToolsData(Experiment $experiment) : bool {
        $data = $experiment->response->toolsData;
        return 'store_company' === ($data[0]['name'] ?? '')
            && 'ACME' === ($data[0]['arguments']['name'] ?? '')
            && 2020 === (int) ($data[0]['arguments']['year'] ?? 0);
    }

    private function validateDefault(Experiment $experiment) : bool {
        $decoded = json_decode($experiment->response->json(), true);
        return $this->expectations['name'] === ($decoded['name'] ?? '')
            && $this->expectations['foundingYear'] === ($decoded['year'] ?? 0);
    }

    private function validateText(Experiment $experiment) : bool {
        return Str::contains(
            $experiment->response->content(),
            [
                $this->expectations['name'],
                (string) $this->expectations['foundingYear']
            ]
        );
    }
}
