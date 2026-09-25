<?php

declare(strict_types=1);

namespace Cognesy\Examples\DecisionEvaluation;

final class DecisionEvaluationReport
{
    private const float MINIMUM_PROBABILITY = 0.000000000001;

    /**
     * @param list<array{
     *   caseId: string,
     *   noul: array{target: int, probability: float, confidence: float, correct: bool},
     *   choice: array{target: string, predicted: string, probabilities: array<string, float>, confidence: float, correct: bool},
     *   score: array{target: int, value: float, probabilities: list<float>, confidence: float, correct: bool},
     *   actionSignalCount: int,
     *   latencyMs: float,
     *   serviceMs: ?float,
     *   queueMs: ?float,
     *   batcherMs: ?float,
     * }> $observations
     * @param  list<array{caseId: string, category: string, statusCode: ?int, retriable: bool}>  $failures
     * @return array<string, mixed>
     */
    public static function summarize(
        string $preset,
        string $driver,
        string $requestedModel,
        array $capabilities,
        array $returnedModels,
        array $observations,
        array $failures,
        int $attempts,
        int $retries,
        float $reviewThreshold = 0.7,
    ): array {
        $noul = array_column($observations, 'noul');
        $choice = array_column($observations, 'choice');
        $score = array_column($observations, 'score');

        return [
            'schemaVersion' => 1,
            'generatedAt' => gmdate(DATE_ATOM),
            'provider' => self::providerSummary($preset, $driver, $requestedModel, $capabilities, $returnedModels),
            'corpus' => self::corpusSummary(),
            'execution' => self::executionSummary($observations, $failures, $attempts, $retries),
            'quality' => self::qualitySummary($noul, $choice, $score),
            'applicationPolicy' => self::applicationPolicySummary(
                $noul,
                $choice,
                $score,
                $observations,
                $reviewThreshold,
            ),
            'interpretation' => self::interpretationSummary(),
        ];
    }

    /** @param array<string, mixed> $capabilities @param list<string> $returnedModels */
    private static function providerSummary(
        string $preset,
        string $driver,
        string $requestedModel,
        array $capabilities,
        array $returnedModels,
    ): array {
        return [
            'preset' => $preset,
            'driver' => $driver,
            'requestedModel' => $requestedModel,
            'returnedModels' => array_values(array_unique($returnedModels)),
            'capabilities' => $capabilities,
        ];
    }

    private static function corpusSummary(): array
    {
        return [
            'id' => SupportTriageCorpus::ID,
            'version' => SupportTriageCorpus::VERSION,
            'caseCount' => count(SupportTriageCorpus::cases()),
            'labels' => [
                'noul' => 'refund requested: false or true',
                'choice' => ['billing', 'technical', 'sales'],
                'score' => ['can wait', 'this week', 'today'],
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $observations
     * @param  list<array<string, mixed>>  $failures
     */
    private static function executionSummary(array $observations, array $failures, int $attempts, int $retries): array
    {
        return [
            'succeeded' => count($observations),
            'failed' => count($failures),
            'attempts' => $attempts,
            'retries' => $retries,
            'failures' => $failures,
            'endToEndLatencyMs' => self::distribution(array_column($observations, 'latencyMs')),
            'providerServiceMs' => self::distribution(self::presentValues($observations, 'serviceMs')),
            'providerQueueMs' => self::distribution(self::presentValues($observations, 'queueMs')),
            'providerBatcherMs' => self::distribution(self::presentValues($observations, 'batcherMs')),
        ];
    }

    /**
     * @param  list<array{target: int, probability: float, confidence: float, correct: bool}>  $noul
     * @param  list<array{target: string, predicted: string, probabilities: array<string, float>, confidence: float, correct: bool}>  $choice
     * @param  list<array{target: int, value: float, probabilities: list<float>, confidence: float, correct: bool}>  $score
     */
    private static function qualitySummary(array $noul, array $choice, array $score): array
    {
        return [
            'noul' => self::binaryMetrics($noul),
            'choice' => self::choiceMetrics($choice),
            'score' => self::scoreMetrics($score),
        ];
    }

    /**
     * @param  list<array{confidence: float, correct: bool}>  $noul
     * @param  list<array{confidence: float, correct: bool}>  $choice
     * @param  list<array{confidence: float, correct: bool}>  $score
     * @param  list<array<string, mixed>>  $observations
     */
    private static function applicationPolicySummary(
        array $noul,
        array $choice,
        array $score,
        array $observations,
        float $reviewThreshold,
    ): array {
        return [
            'description' => 'Illustrative review when answer confidence is below the threshold.',
            'reviewThreshold' => $reviewThreshold,
            ...self::reviewSummary($noul, $choice, $score, $reviewThreshold),
            'modelActionSignalCoverage' => self::ratio(
                array_sum(array_column($observations, 'actionSignalCount')),
                count($observations) * 3,
            ),
            'modelActionSignalIsAuthorization' => false,
        ];
    }

    private static function interpretationSummary(): array
    {
        return [
            'contractCompatibilityDoesNotImplyQualityParity' => true,
            'nativeAndProjectedResultsMustBeComparedSeparately' => true,
            'providerTimingIsNotEndToEndLatency' => true,
            'selfHostedPriceIsNotTotalOperatingCost' => true,
        ];
    }

    /** @param list<array{target: int, probability: float, confidence: float, correct: bool}> $rows */
    private static function binaryMetrics(array $rows): array
    {
        $brier = [];
        $loss = [];
        foreach ($rows as $row) {
            $target = (float) $row['target'];
            $brier[] = ($row['probability'] - $target) ** 2;
            $loss[] = self::logLoss($row['probability'], $row['target']);
        }

        return [
            'count' => count($rows),
            'accuracy' => self::accuracy($rows),
            'brierScore' => self::mean($brier),
            'logLoss' => self::mean($loss),
            'expectedCalibrationError' => self::calibrationError($rows),
            'confidenceBuckets' => self::confidenceBuckets($rows),
        ];
    }

    /** @param list<array{target: string, predicted: string, probabilities: array<string, float>, confidence: float, correct: bool}> $rows */
    private static function choiceMetrics(array $rows): array
    {
        $brier = [];
        $loss = [];
        foreach ($rows as $row) {
            $sum = 0.0;
            foreach ($row['probabilities'] as $id => $probability) {
                $expected = match ($id === $row['target']) {
                    true => 1.0,
                    false => 0.0,
                };
                $sum += ($probability - $expected) ** 2;
            }
            $brier[] = $sum / count($row['probabilities']);
            $loss[] = -log(self::boundedProbability($row['probabilities'][$row['target']]));
        }

        return [
            'count' => count($rows),
            'accuracy' => self::accuracy($rows),
            'brierScore' => self::mean($brier),
            'logLoss' => self::mean($loss),
            'expectedCalibrationError' => self::calibrationError($rows),
            'confidenceBuckets' => self::confidenceBuckets($rows),
        ];
    }

    /** @param list<array{target: int, value: float, probabilities: list<float>, confidence: float, correct: bool}> $rows */
    private static function scoreMetrics(array $rows): array
    {
        $absoluteErrors = [];
        $brier = [];
        $loss = [];
        foreach ($rows as $row) {
            $absoluteErrors[] = abs($row['value'] - $row['target']);
            $sum = 0.0;
            foreach ($row['probabilities'] as $level => $probability) {
                $expected = match ($level === $row['target']) {
                    true => 1.0,
                    false => 0.0,
                };
                $sum += ($probability - $expected) ** 2;
            }
            $brier[] = $sum / count($row['probabilities']);
            $loss[] = -log(self::boundedProbability($row['probabilities'][$row['target']]));
        }

        return [
            'count' => count($rows),
            'roundedLevelAccuracy' => self::accuracy($rows),
            'meanAbsoluteError' => self::mean($absoluteErrors),
            'brierScore' => self::mean($brier),
            'logLoss' => self::mean($loss),
            'expectedCalibrationError' => self::calibrationError($rows),
            'confidenceBuckets' => self::confidenceBuckets($rows),
        ];
    }

    /**
     * @param  list<array{confidence: float, correct: bool}>  $noul
     * @param  list<array{confidence: float, correct: bool}>  $choice
     * @param  list<array{confidence: float, correct: bool}>  $score
     */
    private static function reviewSummary(array $noul, array $choice, array $score, float $threshold): array
    {
        $rows = [...$noul, ...$choice, ...$score];
        $reviewed = array_values(array_filter(
            $rows,
            static fn (array $row): bool => $row['confidence'] < $threshold,
        ));
        $autoAccepted = array_values(array_filter(
            $rows,
            static fn (array $row): bool => $row['confidence'] >= $threshold,
        ));

        return [
            'answerCount' => count($rows),
            'humanReviewRate' => self::ratio(count($reviewed), count($rows)),
            'reviewedCorrect' => self::correctCount($reviewed),
            'reviewedIncorrect' => count($reviewed) - self::correctCount($reviewed),
            'autoAcceptedCorrect' => self::correctCount($autoAccepted),
            'autoAcceptedIncorrect' => count($autoAccepted) - self::correctCount($autoAccepted),
        ];
    }

    /** @param list<array{confidence: float, correct: bool}> $rows */
    private static function confidenceBuckets(array $rows): array
    {
        $buckets = [];
        foreach (range(0, 4) as $index) {
            $minimum = $index / 5;
            $maximum = ($index + 1) / 5;
            $bucketRows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => $row['confidence'] >= $minimum
                    && match ($index) {
                        4 => $row['confidence'] <= $maximum,
                        default => $row['confidence'] < $maximum,
                    },
            ));
            if ($bucketRows === []) {
                continue;
            }
            $buckets[] = [
                'minimum' => $minimum,
                'maximum' => $maximum,
                'count' => count($bucketRows),
                'meanConfidence' => self::mean(array_column($bucketRows, 'confidence')),
                'accuracy' => self::accuracy($bucketRows),
            ];
        }

        return $buckets;
    }

    /** @param list<array{confidence: float, correct: bool}> $rows */
    private static function calibrationError(array $rows): ?float
    {
        if ($rows === []) {
            return null;
        }
        $error = 0.0;
        foreach (self::confidenceBuckets($rows) as $bucket) {
            $error += ($bucket['count'] / count($rows))
                * abs($bucket['accuracy'] - $bucket['meanConfidence']);
        }

        return $error;
    }

    /** @param list<array{correct: bool}> $rows */
    private static function accuracy(array $rows): ?float
    {
        return self::ratio(self::correctCount($rows), count($rows));
    }

    /** @param list<array{correct: bool}> $rows */
    private static function correctCount(array $rows): int
    {
        return count(array_filter($rows, static fn (array $row): bool => $row['correct']));
    }

    private static function logLoss(float $probability, int $target): float
    {
        $probability = self::boundedProbability($probability);

        return -($target * log($probability) + (1 - $target) * log(1.0 - $probability));
    }

    private static function boundedProbability(float $probability): float
    {
        return min(1.0 - self::MINIMUM_PROBABILITY, max(self::MINIMUM_PROBABILITY, $probability));
    }

    /** @param list<float> $values */
    private static function distribution(array $values): ?array
    {
        if ($values === []) {
            return null;
        }
        sort($values, SORT_NUMERIC);

        return [
            'minimum' => $values[0],
            'p50' => self::percentile($values, 0.50),
            'p95' => self::percentile($values, 0.95),
            'maximum' => $values[array_key_last($values)],
        ];
    }

    /** @param list<float> $values */
    private static function percentile(array $values, float $quantile): float
    {
        $index = (int) ceil($quantile * count($values)) - 1;

        return $values[max(0, $index)];
    }

    /** @param list<float|int> $values */
    private static function mean(array $values): ?float
    {
        return match ($values) {
            [] => null,
            default => array_sum($values) / count($values),
        };
    }

    private static function ratio(int $numerator, int $denominator): ?float
    {
        return match ($denominator) {
            0 => null,
            default => $numerator / $denominator,
        };
    }

    /**
     * @param  list<array{serviceMs: ?float, queueMs: ?float, batcherMs: ?float}>  $observations
     * @return list<float>
     */
    private static function presentValues(array $observations, string $field): array
    {
        return array_values(array_filter(
            array_column($observations, $field),
            static fn (mixed $value): bool => is_float($value),
        ));
    }
}
