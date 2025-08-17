<?php declare(strict_types=1);

namespace Cognesy\Evals\Observers\Evaluate;

use Adbar\Dot;
use Cognesy\Evals\Contracts\CanGenerateObservations;
use Cognesy\Evals\Enums\FeedbackType;
use Cognesy\Evals\Execution;
use Cognesy\Evals\Feedback\Feedback;
use Cognesy\Evals\Feedback\FeedbackItem;
use Cognesy\Evals\Observation;
use Cognesy\Evals\Utils\CompareNestedArrays;

/**
 * This class evaluates the match between expected and actual arrays
 * and generates observations for precision, recall, and detailed feedback.
 *
 * @template T of Execution
 */
class ArrayMatchEval implements CanGenerateObservations
{
    public function __construct(
        private array $expected,
        private array $metricNames = [],
    ) {}

    /**
     * Checks if the provided subject is an instance of Execution.
     *
     * @param Execution $subject The subject to be checked.
     * @return bool True if the subject is an instance of Execution, false otherwise.
     */
    public function accepts(mixed $subject): bool {
        return $subject instanceof Execution;
    }

    /**
     * Generates a series of observational metrics for the given subject.
     *
     * @param mixed $subject The subject to analyze.
     * @return iterable<Observation> An iterable collection of observational metrics.
     */
    public function observations(mixed $subject): iterable {
        $analysis = $this->analyse($subject);

        yield $this->precision($subject, $analysis);
        yield $this->recall($subject, $analysis);
        yield from $this->critique($subject);
    }

    // INTERNAL /////////////////////////////////////////////////

    private function analyse(Execution $execution) : array {
        $data = $execution->get('response')?->findJsonData()->toArray();
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
            key: $this->metricNames['precision'] ?? 'execution.precision',
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
            key: $this->metricNames['recall'] ?? 'execution.recall',
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
        $data = $execution->get('response')?->findJsonData()->toArray();
        $differences = (new CompareNestedArrays)->compare($this->expected, $data);
        $feedback = $this->makeFeedback($differences) ?? Feedback::none();
        return array_map(
            callback: fn(Observation $observation) => $observation->withMetadata(['executionId' => $execution->id()]),
            array: $feedback->toObservations([
                'executionId' => $execution->id(),
                'key' => $this->metricNames['field_feedback'] ?? 'execution.field_feedback',
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
