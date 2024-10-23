<?php

namespace Cognesy\Instructor\Extras\Evals\Evaluators;

use Adbar\Dot;
use Cognesy\Instructor\Extras\Evals\Contracts\CanProvideExecutionObservations;
use Cognesy\Instructor\Extras\Evals\Enums\FeedbackType;
use Cognesy\Instructor\Extras\Evals\Execution;
use Cognesy\Instructor\Extras\Evals\Feedback\Feedback;
use Cognesy\Instructor\Extras\Evals\Feedback\FeedbackItem;
use Cognesy\Instructor\Extras\Evals\Observation;
use Cognesy\Instructor\Extras\Evals\Utils\CompareNestedArrays;

class ArrayMatchEval implements CanProvideExecutionObservations
{
    public function __construct(
        private array $expected,
    ) {}

    public function observations(Execution $subject): iterable {
        return [
            $this->precision($subject, $this->analyse($subject)),
            $this->recall($subject, $this->analyse($subject)),
            ...$this->critique($subject),
        ];
    }

    // INTERNAL /////////////////////////////////////////////////

    private function analyse(Execution $execution) : array {
        $data = $execution->get('response')?->json()->toArray();
        $totalCount = count((new Dot($data))->flatten());
        $mismatches = (new CompareNestedArrays)->compare($this->expected, $data);
        $mismatchCount = count($mismatches);
        $matchCount = $totalCount - count($mismatches);
        return [
            'true_positives' => $matchCount,
            'false_positives' => $mismatchCount,
            'true_negatives' => 0,
            'false_negatives' => $totalCount - $matchCount,
            'total' => $totalCount,
        ];
    }

    private function precision(Execution $execution, array $analysis) : Observation {
        $precision = $analysis['true_positives'] / ($analysis['true_positives'] + $analysis['false_positives']);
        return Observation::make(
            type: 'metric',
            key: 'execution.precision',
            value: $precision,
            metadata: [
                'executionId' => $execution->id(),
                'unit' => 'percentage',
                'truePositives' => $analysis['true_positives'],
                'falsePositives' => $analysis['false_positives'],
            ],
        );
    }

    private function recall(Execution $execution, array $analysis) : Observation {
        $recall = $analysis['true_positives'] / ($analysis['true_positives'] + $analysis['false_negatives']);
        return Observation::make(
            type: 'metric',
            key: 'execution.recall',
            value: $recall,
            metadata: [
                'executionId' => $execution->id(),
                'unit' => 'percentage',
                'truePositives' => $analysis['true_positives'],
                'falseNegatives' => $analysis['false_negatives'],
            ],
        );
    }

    private function critique(Execution $execution): array {
        $data = $execution->get('response')?->json()->toArray();
        $differences = (new CompareNestedArrays)->compare($this->expected, $data);
        $feedback = $this->makeFeedback($differences) ?? Feedback::none();
        return array_map(
            callback: fn(Observation $observation) => $observation->withMetadata(['executionId' => $execution->id()]),
            array: $feedback->toObservations([
                'executionId' => $execution->id(),
                'key' => 'execution.field_feedback',
            ])
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
