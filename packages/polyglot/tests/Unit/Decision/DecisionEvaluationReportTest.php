<?php

declare(strict_types=1);

use Cognesy\Examples\DecisionEvaluation\DecisionEvaluationReport;

require_once dirname(__DIR__, 5).'/examples/B06_Decisions/ProviderEvaluation/SupportTriageCase.php';
require_once dirname(__DIR__, 5).'/examples/B06_Decisions/ProviderEvaluation/SupportTriageCorpus.php';
require_once dirname(__DIR__, 5).'/examples/B06_Decisions/ProviderEvaluation/DecisionEvaluationReport.php';

it('reports primitive quality transport facts and caller-owned review outcomes separately', function (): void {
    $report = DecisionEvaluationReport::summarize(
        preset: 'fixture',
        driver: 'fixture',
        requestedModel: 'requested',
        capabilities: ['choice' => 'native', 'noul' => 'projected', 'score' => 'native'],
        returnedModels: ['returned@revision', 'returned@revision'],
        observations: [
            evaluationReportObservation(
                caseId: 'correct',
                target: 1,
                probability: 0.8,
                choiceTarget: 'billing',
                choicePredicted: 'billing',
                scoreTarget: 2,
                scoreValue: 1.8,
                confidence: 0.8,
                latencyMs: 20.0,
                serviceMs: 15.0,
                queueMs: 1.0,
                batcherMs: null,
                actionSignalCount: 3,
            ),
            evaluationReportObservation(
                caseId: 'incorrect',
                target: 0,
                probability: 0.7,
                choiceTarget: 'technical',
                choicePredicted: 'sales',
                scoreTarget: 0,
                scoreValue: 1.6,
                confidence: 0.6,
                latencyMs: 40.0,
                serviceMs: 35.0,
                queueMs: null,
                batcherMs: 30.0,
                actionSignalCount: 0,
            ),
        ],
        failures: [[
            'caseId' => 'failed',
            'category' => 'fixture-rate-limit',
            'statusCode' => 429,
            'retriable' => true,
        ]],
        attempts: 4,
        retries: 1,
    );

    expect($report['provider']['returnedModels'])->toBe(['returned@revision'])
        ->and($report['execution']['succeeded'])->toBe(2)
        ->and($report['execution']['failed'])->toBe(1)
        ->and($report['execution']['attempts'])->toBe(4)
        ->and($report['execution']['retries'])->toBe(1)
        ->and($report['execution']['endToEndLatencyMs']['p50'])->toBe(20.0)
        ->and($report['execution']['providerQueueMs']['maximum'])->toBe(1.0)
        ->and($report['execution']['providerBatcherMs']['maximum'])->toBe(30.0)
        ->and($report['quality']['noul']['accuracy'])->toBe(0.5)
        ->and($report['quality']['choice']['accuracy'])->toBe(0.5)
        ->and($report['quality']['score']['roundedLevelAccuracy'])->toBe(0.5)
        ->and($report['applicationPolicy']['answerCount'])->toBe(6)
        ->and($report['applicationPolicy']['humanReviewRate'])->toBe(0.5)
        ->and($report['applicationPolicy']['modelActionSignalCoverage'])->toBe(0.5)
        ->and($report['applicationPolicy']['modelActionSignalIsAuthorization'])->toBeFalse()
        ->and($report['interpretation']['contractCompatibilityDoesNotImplyQualityParity'])->toBeTrue();
});

/** @return array<string, mixed> */
function evaluationReportObservation(
    string $caseId,
    int $target,
    float $probability,
    string $choiceTarget,
    string $choicePredicted,
    int $scoreTarget,
    float $scoreValue,
    float $confidence,
    float $latencyMs,
    ?float $serviceMs,
    ?float $queueMs,
    ?float $batcherMs,
    int $actionSignalCount,
): array {
    return [
        'caseId' => $caseId,
        'noul' => [
            'target' => $target,
            'probability' => $probability,
            'confidence' => $confidence,
            'correct' => ($probability >= 0.5) === (bool) $target,
        ],
        'choice' => [
            'target' => $choiceTarget,
            'predicted' => $choicePredicted,
            'probabilities' => ['billing' => 0.8, 'technical' => 0.1, 'sales' => 0.1],
            'confidence' => $confidence,
            'correct' => $choiceTarget === $choicePredicted,
        ],
        'score' => [
            'target' => $scoreTarget,
            'value' => $scoreValue,
            'probabilities' => [0.1, 0.1, 0.8],
            'confidence' => $confidence,
            'correct' => (int) round($scoreValue) === $scoreTarget,
        ],
        'actionSignalCount' => $actionSignalCount,
        'latencyMs' => $latencyMs,
        'serviceMs' => $serviceMs,
        'queueMs' => $queueMs,
        'batcherMs' => $batcherMs,
    ];
}
