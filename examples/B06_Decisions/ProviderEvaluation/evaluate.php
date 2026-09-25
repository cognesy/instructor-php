<?php

declare(strict_types=1);

require dirname(__DIR__, 2).'/boot.php';
require_once __DIR__.'/SupportTriageCase.php';
require_once __DIR__.'/SupportTriageCorpus.php';
require_once __DIR__.'/DecisionEvaluationReport.php';

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Examples\DecisionEvaluation\DecisionEvaluationReport;
use Cognesy\Examples\DecisionEvaluation\SupportTriageCase;
use Cognesy\Examples\DecisionEvaluation\SupportTriageCorpus;
use Cognesy\Polyglot\Decision\Config\DecisionConfig;
use Cognesy\Polyglot\Decision\Config\DecisionRetryPolicy;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Data\DecisionResponse;
use Cognesy\Polyglot\Decision\DecisionRuntime;
use Cognesy\Polyglot\Decision\Events\DecisionAttemptStarted;
use Cognesy\Polyglot\Decision\Exceptions\DecisionProviderException;
use Cognesy\Polyglot\Decision\Models\ModelCatalog;

$preset = match (true) {
    isset($argv[1]) && trim($argv[1]) !== '' => trim($argv[1]),
    default => 'typesafe',
};
$config = DecisionConfig::fromPreset($preset);
$models = ModelCatalog::discover();
$profile = $models->find($config->driver, $config->model);
$events = new EventDispatcher("decision.evaluation.{$preset}");
$attempts = 0;
$retries = 0;
$events->wiretap(static function (object $event) use (&$attempts, &$retries): void {
    if (! $event instanceof DecisionAttemptStarted) {
        return;
    }
    $attempts++;
    if ($event->isRetry()) {
        $retries++;
    }
});
$runtime = DecisionRuntime::fromConfig(
    config: $config,
    events: $events,
    models: $models,
);
$observations = [];
$failures = [];
$returnedModels = [];

foreach (SupportTriageCorpus::cases() as $case) {
    $started = hrtime(true);
    try {
        $response = $runtime->create(new DecisionRequest(
            input: $case->input,
            questions: SupportTriageCorpus::questions(),
            retryPolicy: new DecisionRetryPolicy(
                maxAttempts: 3,
                baseDelayMs: 100,
                maxDelayMs: 1000,
                jitter: 'none',
            ),
        ))->response();
        $latencyMs = (hrtime(true) - $started) / 1_000_000;
        $returnedModels[] = $response->model();
        $observations[] = evaluationObservation($case, $response, $latencyMs);
    } catch (DecisionProviderException $error) {
        $failures[] = [
            'caseId' => $case->id,
            'category' => $error::class,
            'statusCode' => $error->statusCode,
            'retriable' => $error->isRetriable(),
        ];
    } catch (Throwable $error) {
        $failures[] = [
            'caseId' => $case->id,
            'category' => $error::class,
            'statusCode' => null,
            'retriable' => false,
        ];
    }
}

$report = DecisionEvaluationReport::summarize(
    preset: $preset,
    driver: $config->driver,
    requestedModel: $config->model,
    capabilities: $profile->capabilities->toArray(),
    returnedModels: $returnedModels,
    observations: $observations,
    failures: $failures,
    attempts: $attempts,
    retries: $retries,
);

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";

/** @return array<string, mixed> */
function evaluationObservation(
    SupportTriageCase $case,
    DecisionResponse $response,
    float $latencyMs,
): array {
    $answers = $response->answers();
    $noul = $answers->noul('refund_requested');
    $choice = $answers->choice('department');
    $score = $answers->score('urgency');
    $choiceProbabilities = [];
    foreach ($choice->probabilities()->all() as $entry) {
        $choiceProbabilities[$entry['id']] = $entry['probability'];
    }
    $headers = normalizedHeaders($response->responseData()->headers());

    return [
        'caseId' => $case->id,
        'noul' => [
            'target' => (int) $case->refundRequested,
            'probability' => $noul->probability(),
            'confidence' => $noul->confidence(),
            'correct' => ($noul->probability() >= 0.5) === $case->refundRequested,
        ],
        'choice' => [
            'target' => $case->department,
            'predicted' => $choice->value(),
            'probabilities' => $choiceProbabilities,
            'confidence' => $choice->probabilities()->highest(),
            'correct' => $choice->value() === $case->department,
        ],
        'score' => [
            'target' => $case->urgency,
            'value' => $score->value(),
            'probabilities' => $score->probabilities()->all(),
            'confidence' => max($score->probabilities()->all()),
            'correct' => (int) round($score->value()) === $case->urgency,
        ],
        'actionSignalCount' => count(array_filter([
            $noul->signals()->modelActionProbability(),
            $choice->signals()->modelActionProbability(),
            $score->signals()->modelActionProbability(),
        ], static fn (?float $value): bool => $value !== null)),
        'latencyMs' => $latencyMs,
        'serviceMs' => firstTiming($headers, ['x-laya-server-ms', 'x-jeff-server-ms']),
        'queueMs' => firstTiming($headers, ['x-laya-queue-ms']),
        'batcherMs' => firstTiming($headers, ['x-jeff-batcher-ms']),
    ];
}

/** @param array<string, string|array<string>> $headers */
function normalizedHeaders(array $headers): array
{
    $normalized = [];
    foreach ($headers as $name => $value) {
        $normalized[strtolower($name)] = match (is_array($value)) {
            true => $value[0] ?? '',
            false => $value,
        };
    }

    return $normalized;
}

/** @param array<string, string> $headers @param list<string> $names */
function firstTiming(array $headers, array $names): ?float
{
    foreach ($names as $name) {
        $value = $headers[$name] ?? null;
        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }
    }

    return null;
}
