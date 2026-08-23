<?php declare(strict_types=1);

/**
 * Pins the INCREMENTAL streaming-usage branch.
 *
 * CohereV2ResponseAdapter is the only bundled adapter that leaves
 * PartialInferenceDelta::$usageIsCumulative false (see its hasUsageData()
 * docblock), so it is the only adapter that reaches
 * StreamingUsageState::applyIncremental(). That branch had no stream fixture,
 * which is why a research pass misread it as dead code.
 *
 * These tests must FAIL if applyIncremental() is replaced by applyCumulative().
 */

use Cognesy\Polyglot\Inference\Drivers\CohereV2\CohereV2ResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\CohereV2\CohereV2UsageFormat;
use Cognesy\Polyglot\Inference\Streaming\InferenceStreamState;

function cohereUsageEvent(int $input, int $output): string {
    return json_encode([
        'delta' => [
            'message' => ['content' => ['text' => '']],
            'usage' => ['billed_units' => [
                'input_tokens' => $input,
                'output_tokens' => $output,
            ]],
        ],
    ]);
}

it('Cohere V2: sums streamed usage fragments rather than taking their maximum', function () {
    $adapter = new CohereV2ResponseAdapter(new CohereV2UsageFormat());
    $state = new InferenceStreamState();

    // Deliberately non-monotonic: a cumulative (max) reading would report
    // input=5/output=7, an incremental (sum) reading reports input=9/output=12.
    $events = [
        cohereUsageEvent(5, 7),
        cohereUsageEvent(1, 2),
        cohereUsageEvent(3, 3),
    ];

    foreach ($adapter->fromStreamDeltas($events) as $delta) {
        $state->applyDelta($delta);
    }

    $usage = $state->finalResponse()->usage();

    expect($usage->inputTokens)->toBe(9)   // 5+1+3, NOT max(5,1,3)=5
        ->and($usage->outputTokens)->toBe(12); // 7+2+3, NOT max(7,2,3)=7
});

it('Cohere V2: leaves usageIsCumulative false on streamed deltas', function () {
    $adapter = new CohereV2ResponseAdapter(new CohereV2UsageFormat());

    $deltas = iterator_to_array($adapter->fromStreamDeltas([cohereUsageEvent(4, 6)]));

    expect($deltas)->not->toBeEmpty()
        ->and($deltas[0]->usage)->not->toBeNull()
        ->and($deltas[0]->usageIsCumulative)->toBeFalse();
});
