<?php declare(strict_types=1);

use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputResponseGenerated;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;

class CrossPathProjectorUser
{
    public string $name = '';
}

/**
 * The sync and streaming paths used to build response.generated payloads from two
 * byte-identical copies of the same builder, which had drifted: one resolved attemptId
 * active-first, the other finalized-first. Both now go through
 * StructuredOutputEventProjector, so correlation identifiers must agree.
 */
it('emits the same correlation identifiers for response.generated on sync and streaming paths', function () {
    $capture = function (bool $streamed): array {
        $payload = null;
        $driver = match ($streamed) {
            true => new FakeInferenceDriver(
                responses: [],
                streamBatches: [[
                    new PartialInferenceDelta(contentDelta: '{"name":'),
                    new PartialInferenceDelta(contentDelta: '"Ada"}'),
                ]],
            ),
            false => new FakeInferenceDriver([
                new InferenceResponse(content: '{"name":"Ada"}', finishReason: 'stop'),
            ]),
        };

        $runtime = makeStructuredRuntime(driver: $driver, outputMode: OutputMode::Json)
            ->onEvent(
                StructuredOutputResponseGenerated::class,
                function (StructuredOutputResponseGenerated $event) use (&$payload): void {
                    $payload ??= $event->data;
                },
            );

        $pending = (new StructuredOutput($runtime))
            ->withMessages('Extract the user.')
            ->withResponseClass(CrossPathProjectorUser::class)
            ->withStreaming($streamed)
            ->create();

        // response.generated is emitted when the stream is drained to its final response,
        // which is the streaming counterpart of the sync get().
        match ($streamed) {
            true => $pending->stream()->finalResponse(),
            false => $pending->get(),
        };

        expect($payload)->toBeArray();

        return $payload;
    };

    $sync = $capture(false);
    $streamed = $capture(true);

    // Identifiers are per-execution, so compare their structure, not their literal values.
    expect($sync['phase'])->toBe('response.generated')
        ->and($streamed['phase'])->toBe('response.generated');

    // The rule under test: attemptId resolves the same way on both paths, and phaseId
    // embeds it identically.
    expect($sync)->toHaveKey('attemptId')
        ->and($streamed)->toHaveKey('attemptId')
        ->and($sync['phaseId'])->toBe("{$sync['executionId']}:response.generated:{$sync['attemptId']}")
        ->and($streamed['phaseId'])->toBe("{$streamed['executionId']}:response.generated:{$streamed['attemptId']}");

    // Both paths must agree on which attempt the terminal event belongs to: the single
    // finalized attempt, not a stale or absent one.
    expect($sync['attemptId'])->not->toBeEmpty()
        ->and($streamed['attemptId'])->not->toBeEmpty();

    // And they must expose the same payload shape.
    expect(array_keys($streamed))->toEqualCanonicalizing(array_keys($sync));
})->group('structured-output-contract-regression');
