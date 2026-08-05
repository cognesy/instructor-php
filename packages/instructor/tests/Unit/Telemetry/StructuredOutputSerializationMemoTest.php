<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\Support\FakeInferenceDriver;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Telemetry\MessagesSerializationMemo;

final class MemoCountDto
{
    public string $name;
}

/**
 * Runs one full structured-output request with a wiretap attached and reports how many
 * conversations the memo actually serialised.
 *
 * The count is read as a delta, never reset: the memo is process-global by design and other
 * tests in the same process warm it. A delta is correct either way, because the conversation
 * built here is a fresh instance and therefore always misses first.
 */
function memoMissesForOneStructuredRequest(EventDispatcher $events): int
{
    $runtime = makeStructuredRuntime(
        driver: new FakeInferenceDriver([new InferenceResponse(content: '{"name":"Ava"}')]),
        events: $events,
        outputMode: OutputMode::Json,
    );

    $before = MessagesSerializationMemo::serialisationCount();

    (new StructuredOutput($runtime))
        ->with(
            messages: [
                ['role' => 'user', 'content' => 'Extract a name from: Ava Nowak, 31.'],
                ['role' => 'assistant', 'content' => 'Sure.'],
                ['role' => 'user', 'content' => 'Now do it.'],
            ],
            responseModel: MemoCountDto::class,
        )
        ->get();

    return MessagesSerializationMemo::serialisationCount() - $before;
}

it('serialises the conversation twice per structured-output request, not seven times', function () {
    $events = new EventDispatcher();
    $events->wiretap(static function (object $event): void {
        // Force every telemetry envelope to be built: the eexl.4 gates skip envelope
        // construction entirely when nothing listens, which would hide the memo's effect.
        $_ = $event->data ?? null;
    });

    // Seven telemetry sites serialise a conversation on this path: three in
    // StructuredOutputTelemetry (requestReceived, executionStarted, responseGenerated) and
    // four in InferenceTelemetry (execution started/completed, attempt started/succeeded).
    // They see two distinct conversations -- the structured-output request's, and the
    // materialized one handed to the nested Inference call -- so two is the floor.
    expect(memoMissesForOneStructuredRequest($events))->toBe(2);
})->group('telemetry');

it('serialises nothing when no listeners are attached', function () {
    // Zero, not one: instructor's three structured-output emitters are listener-gated
    // (eexl.20), so no envelope is built and the conversation is never touched. Before that
    // gating the same request cost one serialisation with the memo and three without it.
    expect(memoMissesForOneStructuredRequest(new EventDispatcher()))->toBe(0);
})->group('telemetry');

it('never counts messages by serialising the conversation', function () {
    $src = __DIR__ . '/../../../src';
    $offenders = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src));
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $body = (string) file_get_contents($file->getPathname());
        if (preg_match('/count\(\s*\$\w+(->\w+\(\))*->messages\(\)->toArray\(\)/', $body) === 1) {
            $offenders[] = $file->getPathname();
        }
    }

    // Messages implements Countable. count($messages) is the same answer without paying
    // ~115 microseconds to serialise a 128 KB conversation and throw the result away.
    expect($offenders)->toBe([]);
})->group('telemetry');
