<?php

namespace Cognesy\Instructor\Extras\Evals\Evaluators;

use Adbar\Dot;
use Cognesy\Instructor\Extras\Evals\Contracts\CanObserveExecution;
use Cognesy\Instructor\Extras\Evals\Enums\FeedbackType;
use Cognesy\Instructor\Extras\Evals\Execution;
use Cognesy\Instructor\Extras\Evals\Feedback\Feedback;
use Cognesy\Instructor\Extras\Evals\Feedback\FeedbackItem;
use Cognesy\Instructor\Extras\Evals\Observation;
use Cognesy\Instructor\Extras\Evals\Utils\CompareNestedArrays;

class ArrayMatchEval implements CanObserveExecution
{
    public function __construct(
        private string $name,
        private array $expected,
    ) {}

    public function observe(Execution $execution) : Observation {
        $data = $execution->get('response')?->json()->toArray();
        $differences = (new CompareNestedArrays)->compare($this->expected, $data);
        $total = count((new Dot($data))->flatten());
        $matches = $total - count($differences);
        return Observation::make(
            type: 'metric',
            key: $this->name,
            value: $matches / $total,
            metadata: [
                'executionId' => $execution->id(),
                'matchedFields' => $matches,
                'totalFields' => $total,
                'unit' => 'percentage',
            ],
        );
    }

    // INTERNAL /////////////////////////////////////////////////

    private function critique(Execution $execution): array {
        $data = $execution->get('response')?->json()->toArray();
        $differences = (new CompareNestedArrays)->compare($this->expected, $data);
        $feedback = $this->makeFeedback($differences) ?? Feedback::none();
        return array_map(
            callback: fn(Observation $observation) => $observation->withMetadata(['executionId' => $execution->id()]),
            array: $feedback->toObservations()
        );
    }

    private function makeFeedback(array $differences) : Feedback {
        $feedback = new Feedback();
        foreach($differences as $path => $diff) {
            $varFeedback = $this->getFeedback($path, $diff['expected'], $diff['actual']);
            if ($varFeedback) {
                $feedback->add($varFeedback);
            }
        }
        return $feedback;
    }

    private function getFeedback(string $key, mixed $expectedVal, mixed $actualVal) : ?FeedbackItem {
        return match(true) {
            ($expectedVal !== null) && ($actualVal === null) => new FeedbackItem(
                context: $key,
                feedback: "Expected `$key`, but param not found in result",
                category: FeedbackType::Error
            ),
            ($actualVal !== $expectedVal) => new FeedbackItem(
                context: $key,
                feedback: "Expected `$key` value `$expectedVal`, but actual is `$actualVal`",
                category: FeedbackType::Error
            ),
            default => null,
        };
    }
}
