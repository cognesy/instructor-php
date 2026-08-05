<?php declare(strict_types=1);

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
use Cognesy\Polyglot\Inference\Streaming\StreamingUsageState;

/**
 * Per-delta usage guard contract for every streaming response adapter.
 *
 * Usage arrives on a handful of events per stream (roughly 1 chunk in 943 on a
 * real one). Adapters must pass `usage: null` for every other delta rather than
 * allocating an all-zero InferenceUsage, which StreamingUsageState::apply()
 * discards anyway. See research/plans/polyglot-improvements/02-event-and-telemetry-guards.md.
 *
 * The predicate is provider-specific -- Gemini reads `usageMetadata`, Anthropic
 * splits across `message.usage` and `usage`, Cohere v2 uses `delta.usage`,
 * OpenResponses uses `response.usage`. Each case below pins its own key, so a
 * copy-pasted guard that happens to check the wrong one fails here.
 *
 * The allocation count itself is gated by
 * tests/Benchmarks/StreamAdapterUsageAllocationTest.php.
 */

/**
 * @return array<string, array{adapter: object, quiet: string, carrying: list<string>, input: int, output: int}>
 */
function streamUsageGuardCases(): array {
    return [
        'OpenAI' => [
            'adapter' => new OpenAIResponseAdapter(new OpenAIUsageFormat()),
            'quiet' => json_encode(['choices' => [['delta' => ['content' => 'x']]]]),
            'carrying' => [json_encode([
                'choices' => [['delta' => ['content' => 'x'], 'finish_reason' => 'stop']],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 20],
            ])],
            'input' => 10,
            'output' => 20,
        ],
        'OpenAICompatibleReasoning' => [
            'adapter' => new OpenAICompatibleReasoningAdapter(new OpenAIUsageFormat()),
            'quiet' => json_encode(['choices' => [['delta' => ['reasoning_content' => 'x']]]]),
            'carrying' => [json_encode([
                'choices' => [['delta' => ['content' => 'x'], 'finish_reason' => 'stop']],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 20],
            ])],
            'input' => 10,
            'output' => 20,
        ],
        // Gemini's key is usageMetadata -- a guard on `usage` would never fire.
        'Gemini' => [
            'adapter' => new GeminiResponseAdapter(new GeminiUsageFormat()),
            'quiet' => json_encode(['candidates' => [['content' => ['parts' => [['text' => 'x']]]]]]),
            'carrying' => [json_encode([
                'candidates' => [['content' => ['parts' => [['text' => 'x']]], 'finishReason' => 'STOP']],
                'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 20],
            ])],
            'input' => 10,
            'output' => 20,
        ],
        // Anthropic splits usage across two events. A guard checking only `usage`
        // would drop the input-token count that arrives on message_start.
        'Anthropic' => [
            'adapter' => new AnthropicResponseAdapter(new AnthropicUsageFormat()),
            'quiet' => json_encode([
                'type' => 'content_block_delta',
                'index' => 0,
                'delta' => ['type' => 'text_delta', 'text' => 'x'],
            ]),
            'carrying' => [
                json_encode([
                    'type' => 'message_start',
                    'message' => ['usage' => ['input_tokens' => 10, 'output_tokens' => 0]],
                ]),
                json_encode([
                    'type' => 'message_delta',
                    'delta' => ['stop_reason' => 'end_turn'],
                    'usage' => ['output_tokens' => 20],
                ]),
            ],
            'input' => 10,
            'output' => 20,
        ],
        // Cohere v2 nests streamed usage under delta.usage, and leaves
        // usageIsCumulative false, so the incremental branch applies.
        'CohereV2' => [
            'adapter' => new CohereV2ResponseAdapter(new CohereV2UsageFormat()),
            'quiet' => json_encode(['delta' => ['message' => ['content' => ['text' => 'x']]]]),
            'carrying' => [json_encode([
                'delta' => [
                    'finish_reason' => 'COMPLETE',
                    'usage' => ['billed_units' => ['input_tokens' => 10, 'output_tokens' => 20]],
                ],
            ])],
            'input' => 10,
            'output' => 20,
        ],
        // OpenResponses carries usage on the terminal response.completed event.
        'OpenResponses' => [
            'adapter' => new OpenResponsesResponseAdapter(new OpenResponsesUsageFormat()),
            'quiet' => json_encode([
                'type' => 'response.output_text.delta',
                'item_id' => 'msg_1',
                'delta' => 'x',
            ]),
            'carrying' => [json_encode([
                'type' => 'response.completed',
                'response' => [
                    'status' => 'completed',
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 20],
                ],
            ])],
            'input' => 10,
            'output' => 20,
        ],
    ];
}

it('passes usage: null for deltas that carry no usage payload', function (string $provider) {
    $case = streamUsageGuardCases()[$provider];

    $deltas = iterator_to_array($case['adapter']->fromStreamDeltas([$case['quiet']]), false);

    expect($deltas)->not->toBeEmpty("{$provider}: quiet event produced no delta");
    foreach ($deltas as $delta) {
        expect($delta->usage)->toBeNull(
            "{$provider}: a delta with no usage payload must not allocate an InferenceUsage",
        );
    }
})->with(array_keys(streamUsageGuardCases()));

it('still builds usage for the deltas that do carry it', function (string $provider) {
    $case = streamUsageGuardCases()[$provider];

    $deltas = iterator_to_array($case['adapter']->fromStreamDeltas($case['carrying']), false);
    $withUsage = array_values(array_filter($deltas, fn($d) => $d->usage !== null));

    expect($withUsage)->not->toBeEmpty(
        "{$provider}: the usage-carrying event yielded no delta with usage -- the guard "
        . 'predicate does not match the key its usage format reads',
    );
})->with(array_keys(streamUsageGuardCases()));

it('assembles the same final usage as an unguarded stream would', function (string $provider) {
    $case = streamUsageGuardCases()[$provider];

    // A realistic stream: many quiet deltas, then the usage-carrying ones. Every
    // quiet delta used to contribute an all-zero InferenceUsage; the assembled
    // total must be unchanged now that they contribute null.
    $bodies = array_merge(array_fill(0, 50, $case['quiet']), $case['carrying']);

    $state = new StreamingUsageState();
    foreach ($case['adapter']->fromStreamDeltas($bodies) as $delta) {
        $state->apply($delta->usage, $delta->usageIsCumulative);
    }

    expect($state->inputTokens())->toBe($case['input'], "{$provider}: input tokens");
    expect($state->outputTokens())->toBe($case['output'], "{$provider}: output tokens");
})->with(array_keys(streamUsageGuardCases()));
