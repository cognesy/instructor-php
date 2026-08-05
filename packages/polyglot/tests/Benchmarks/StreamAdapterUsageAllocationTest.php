<?php declare(strict_types=1);

use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\Anthropic\AnthropicUsageFormat;
use Cognesy\Polyglot\Inference\Drivers\CohereV2\CohereV2ResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\CohereV2\CohereV2UsageFormat;
use Cognesy\Polyglot\Inference\Drivers\Gemini\GeminiResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\Gemini\GeminiUsageFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIUsageFormat;
use Cognesy\Polyglot\Inference\Drivers\OpenAICompatible\OpenAICompatibleReasoningAdapter;
use Cognesy\Polyglot\Inference\Drivers\OpenResponses\OpenResponsesResponseAdapter;
use Cognesy\Polyglot\Inference\Drivers\OpenResponses\OpenResponsesUsageFormat;
use Cognesy\Utils\Profiler\ObjectCreationTrace;

/**
 * Per-adapter InferenceUsage allocation profile for the streaming hot path.
 *
 * WHY THIS EXISTS
 * StreamingScaleProfileTest drives FakeInferenceDriver, which yields
 * PartialInferenceDelta objects directly and never touches a response adapter.
 * It therefore cannot observe how many InferenceUsage objects an adapter builds
 * per delta -- it reports 1 for every provider. This profile closes that gap by
 * running real event bodies through the real adapters.
 *
 * WHAT IT GUARDS
 * Usage data appears in roughly 1 chunk in 943 on a real stream. Commit
 * 6c7bf364d made OpenAIResponseAdapter pass `usage: null` for deltas with no
 * usage payload, because a zero-valued InferenceUsage is a no-op for
 * StreamingUsageState and allocating one per delta was pure waste. A healthy
 * adapter constructs O(1) InferenceUsage objects across a stream, not O(deltas).
 *
 * STATUS
 * instructor-eexl.2 brought all six adapters to O(1). This test now ENFORCES
 * that: every adapter must stay under USAGE_OBJECT_CEILING for a 1,000-delta
 * stream. Before the fix, five of the six built exactly 1,000.
 */

// Anthropic legitimately builds two (message_start carries input tokens,
// message_delta carries output tokens). The ceiling is well clear of that and
// still three orders of magnitude below a per-delta regression.
const USAGE_OBJECT_CEILING = 10;

const USAGE_PROFILE_DELTA_COUNT = 1_000;

/**
 * @param callable(int): list<string> $eventBodyFactory
 * @return array{adapter: string, deltas: int, usage_objects: int, per_delta: float}
 */
function profileAdapterUsageAllocation(
    string $label,
    object $adapter,
    callable $eventBodyFactory,
): array {
    $bodies = $eventBodyFactory(USAGE_PROFILE_DELTA_COUNT);

    gc_collect_cycles();
    ObjectCreationTrace::enable([InferenceUsage::class]);

    $deltas = 0;
    foreach ($adapter->fromStreamDeltas($bodies) as $_delta) {
        $deltas++;
    }

    $usageObjects = ObjectCreationTrace::createdCount(InferenceUsage::class);
    ObjectCreationTrace::reset();

    return [
        'adapter' => $label,
        'deltas' => $deltas,
        'usage_objects' => $usageObjects,
        'per_delta' => $deltas > 0 ? $usageObjects / $deltas : 0.0,
    ];
}

/**
 * OpenAI-shaped chunks: only the last one carries a usage block.
 *
 * @return list<string>
 */
function openAiStreamBodies(int $count): array {
    $bodies = [];
    for ($i = 0; $i < $count - 1; $i++) {
        $bodies[] = json_encode(['choices' => [['delta' => ['content' => 'x']]]]);
    }
    $bodies[] = json_encode([
        'choices' => [['delta' => ['content' => 'x'], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 20],
    ]);

    return $bodies;
}

/** @return list<string> */
function geminiStreamBodies(int $count): array {
    $bodies = [];
    for ($i = 0; $i < $count - 1; $i++) {
        $bodies[] = json_encode(['candidates' => [['content' => ['parts' => [['text' => 'x']]]]]]);
    }
    $bodies[] = json_encode([
        'candidates' => [['content' => ['parts' => [['text' => 'x']]], 'finishReason' => 'STOP']],
        'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 20],
    ]);

    return $bodies;
}

/** @return list<string> */
function anthropicStreamBodies(int $count): array {
    $bodies = [json_encode([
        'type' => 'message_start',
        'message' => ['usage' => ['input_tokens' => 10, 'output_tokens' => 0]],
    ])];
    for ($i = 0; $i < $count - 2; $i++) {
        $bodies[] = json_encode([
            'type' => 'content_block_delta',
            'index' => 0,
            'delta' => ['type' => 'text_delta', 'text' => 'x'],
        ]);
    }
    $bodies[] = json_encode([
        'type' => 'message_delta',
        'delta' => ['stop_reason' => 'end_turn'],
        'usage' => ['output_tokens' => 20],
    ]);

    return $bodies;
}

/**
 * OpenResponses: usage arrives on the terminal `response.completed` event under
 * `response.usage`, never on the `*.delta` events.
 *
 * @return list<string>
 */
function openResponsesStreamBodies(int $count): array {
    $bodies = [];
    for ($i = 0; $i < $count - 1; $i++) {
        $bodies[] = json_encode([
            'type' => 'response.output_text.delta',
            'item_id' => 'msg_1',
            'delta' => 'x',
        ]);
    }
    $bodies[] = json_encode([
        'type' => 'response.completed',
        'response' => [
            'status' => 'completed',
            'usage' => ['input_tokens' => 10, 'output_tokens' => 20],
        ],
    ]);

    return $bodies;
}

/** @return list<string> */
function cohereStreamBodies(int $count): array {
    $bodies = [];
    for ($i = 0; $i < $count - 1; $i++) {
        $bodies[] = json_encode(['delta' => ['message' => ['content' => ['text' => 'x']]]]);
    }
    $bodies[] = json_encode([
        'delta' => [
            'finish_reason' => 'COMPLETE',
            'usage' => ['billed_units' => ['input_tokens' => 10, 'output_tokens' => 20]],
        ],
    ]);

    return $bodies;
}

it('profiles InferenceUsage allocation per streaming adapter', function () {
    $profiles = [
        profileAdapterUsageAllocation(
            'OpenAI (guarded in 6c7bf364d)',
            new OpenAIResponseAdapter(new OpenAIUsageFormat()),
            openAiStreamBodies(...),
        ),
        profileAdapterUsageAllocation(
            'OpenResponses',
            new OpenResponsesResponseAdapter(new OpenResponsesUsageFormat()),
            openResponsesStreamBodies(...),
        ),
        profileAdapterUsageAllocation(
            'OpenAICompatibleReasoning',
            new OpenAICompatibleReasoningAdapter(new OpenAIUsageFormat()),
            openAiStreamBodies(...),
        ),
        profileAdapterUsageAllocation(
            'Gemini',
            new GeminiResponseAdapter(new GeminiUsageFormat()),
            geminiStreamBodies(...),
        ),
        profileAdapterUsageAllocation(
            'Anthropic',
            new AnthropicResponseAdapter(new AnthropicUsageFormat()),
            anthropicStreamBodies(...),
        ),
        profileAdapterUsageAllocation(
            'CohereV2',
            new CohereV2ResponseAdapter(new CohereV2UsageFormat()),
            cohereStreamBodies(...),
        ),
    ];

    echo "\n\n  InferenceUsage allocation per streaming adapter (" . number_format(USAGE_PROFILE_DELTA_COUNT) . " event bodies)\n";
    echo "  ┌───────────────────────────────┬────────┬───────────────┬───────────┐\n";
    echo "  │ Adapter                       │ Deltas │ Usage objects │ Per delta │\n";
    echo "  ├───────────────────────────────┼────────┼───────────────┼───────────┤\n";
    foreach ($profiles as $p) {
        echo sprintf(
            "  │ %-29s │ %6s │ %13s │ %9s │\n",
            $p['adapter'],
            number_format($p['deltas']),
            number_format($p['usage_objects']),
            number_format($p['per_delta'], 2),
        );
    }
    echo "  └───────────────────────────────┴────────┴───────────────┴───────────┘\n";

    // Every adapter must have produced deltas -- an adapter yielding nothing would
    // report a flattering zero and silently stop guarding anything.
    foreach ($profiles as $p) {
        expect($p['deltas'])->toBeGreaterThan(0, "{$p['adapter']} produced no deltas");
    }

    // Every adapter must construct O(1) usage objects across the stream, not O(deltas).
    // A failure here means that adapter's hasUsageData() guard is missing, or its
    // predicate no longer matches the key its usage format reads.
    foreach ($profiles as $p) {
        expect($p['usage_objects'])->toBeLessThanOrEqual(USAGE_OBJECT_CEILING, sprintf(
            '%s built %s InferenceUsage objects for %s deltas (%.2f per delta) -- its '
            . 'per-delta usage guard is missing or its hasUsageData() predicate no longer '
            . 'matches the payload key its usage format reads.',
            $p['adapter'],
            number_format($p['usage_objects']),
            number_format($p['deltas']),
            $p['per_delta'],
        ));
    }
});
