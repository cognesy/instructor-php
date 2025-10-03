<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Creation\ResponseModelFactory;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Deserialization\ResponseDeserializer;
use Cognesy\Instructor\Events\PartialsGenerator\PartialResponseGenerated;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedToolCallCompleted;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedToolCallStarted;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedToolCallUpdated;
use Cognesy\Instructor\Streaming\PartialsGenerator;
use Cognesy\Instructor\Transformation\ResponseTransformer;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Schema\Factories\SchemaFactory;
use Cognesy\Schema\Factories\ToolCallBuilder;
use Cognesy\Utils\Result\Result;

class PG_FakeDeserializer extends ResponseDeserializer {
    public function __construct($events, $config) { parent::__construct($events, [], $config); }
    public function deserialize(string $json, ResponseModel $responseModel, ?string $toolName = null) : Result {
        // Return stable object based on JSON to test hashing
        return Result::success((object)['data' => json_decode($json, true)]);
    }
}
class PG_FakeTransformer extends ResponseTransformer {
    public function __construct($events, $config) { parent::__construct($events, [], $config); }
}

function pg_makeResponseModel(): ResponseModel {
    $cfg = new StructuredOutputConfig();
    $schemaFactory = new SchemaFactory(useObjectReferences: $cfg->useObjectReferences());
    $factory = new ResponseModelFactory(new ToolCallBuilder($schemaFactory), $schemaFactory, $cfg, new EventDispatcher());
    return $factory->fromAny(stdClass::class);
}

describe('PartialsGenerator', function () {
    it('emits tool-call started, updated, and completed events', function () {
        $events = Mockery::mock(EventDispatcher::class);
        $eventsLog = [];
        $events->shouldReceive('dispatch')->byDefault()->andReturnUsing(function($e) use (&$eventsLog) {
            $eventsLog[] = get_class($e);
            return $e;
        });

        $cfg = new StructuredOutputConfig();
        $gen = new PartialsGenerator(
            responseDeserializer: new PG_FakeDeserializer($events, $cfg),
            responseTransformer: new PG_FakeTransformer($events, $cfg),
            events: $events,
        );

        $rm = pg_makeResponseModel();

        $stream = (function() {
            // Start a tool call
            yield new PartialInferenceResponse(toolName: 'fn');
            // Provide arguments delta
            yield new PartialInferenceResponse(contentDelta: '{"a":1}');
            // Signal tool still the same; finalize previous call
            yield new PartialInferenceResponse(toolName: 'fn');
        })();

        iterator_to_array($gen->getPartialResponses($stream, $rm));

        // Check event presence and relative ordering
        $startedIdx = array_search(StreamedToolCallStarted::class, $eventsLog, true);
        $updatedIdx = array_search(StreamedToolCallUpdated::class, $eventsLog, true);
        $completedIdx = array_search(StreamedToolCallCompleted::class, $eventsLog, true);

        expect($startedIdx)->not->toBeFalse()
            ->and($updatedIdx)->not->toBeFalse()
            ->and($completedIdx)->not->toBeFalse()
            ->and($startedIdx)->toBeLessThan($updatedIdx)
            ->and($updatedIdx)->toBeLessThan($completedIdx);
    });

    it('emits PartialResponseGenerated only when object hash changes', function () {
        $events = Mockery::mock(EventDispatcher::class);
        $genEvents = [];
        $events->shouldReceive('dispatch')->byDefault()->andReturnUsing(function($e) use (&$genEvents) {
            if ($e instanceof PartialResponseGenerated) { $genEvents[] = $e; }
            return $e;
        });

        $cfg = new StructuredOutputConfig();
        $gen = new PartialsGenerator(
            responseDeserializer: new PG_FakeDeserializer($events, $cfg),
            responseTransformer: new PG_FakeTransformer($events, $cfg),
            events: $events,
        );

        $rm = pg_makeResponseModel();

        $stream = (function() {
            // First chunk with valid JSON
            yield new PartialInferenceResponse(contentDelta: '{"a":1}');
            // Second chunk introduces another valid JSON equal to the first
            yield new PartialInferenceResponse(contentDelta: "\n{\"a\":1}");
        })();

        iterator_to_array($gen->getPartialResponses($stream, $rm));

        // Only the first distinct object should emit PartialResponseGenerated
        expect(count($genEvents))->toBe(1);
    });

    it('starts implicit tool when args arrive first using default tool name', function () {
        $events = Mockery::mock(EventDispatcher::class);
        $log = [];
        $events->shouldReceive('dispatch')->byDefault()->andReturnUsing(function($e) use (&$log) {
            $log[] = [$e::class, $e->data];
            return $e;
        });

        $cfg = new StructuredOutputConfig();
        $gen = new PartialsGenerator(new PG_FakeDeserializer($events, $cfg), new PG_FakeTransformer($events, $cfg), $events);
        $rm = pg_makeResponseModel();

        $stream = (function() {
            yield new PartialInferenceResponse(contentDelta: '{"k":1}');
        })();

        iterator_to_array($gen->getPartialResponses($stream, $rm));

        $started = array_values(array_filter($log, fn($e) => $e[0] === StreamedToolCallStarted::class));
        $updated = array_values(array_filter($log, fn($e) => $e[0] === StreamedToolCallUpdated::class));
        expect($started)->not->toBeEmpty();
        // default tool name from config
        $startedName = $started[0][1]['toolCall']['name'] ?? '';
        expect($startedName)->toBe($cfg->toolName());
        // ensure update followed start
        $startedIdx = array_search($started[0], $log, true);
        $updatedIdx = array_search($updated[0], $log, true);
        expect($startedIdx)->toBeLessThan($updatedIdx);
    });

    it('finalizes previous tool on new tool name and starts another', function () {
        $events = Mockery::mock(EventDispatcher::class);
        $log = [];
        $events->shouldReceive('dispatch')->byDefault()->andReturnUsing(function($e) use (&$log) {
            $log[] = [$e::class, $e->data];
            return $e;
        });

        $cfg = new StructuredOutputConfig();
        $gen = new PartialsGenerator(new PG_FakeDeserializer($events, $cfg), new PG_FakeTransformer($events, $cfg), $events);
        $rm = pg_makeResponseModel();

        $stream = (function() {
            yield new PartialInferenceResponse(toolName: 'toolA');
            yield new PartialInferenceResponse(contentDelta: '{"x":1}');
            // trigger finalize of A and start B
            yield new PartialInferenceResponse(toolName: 'toolB');
            yield new PartialInferenceResponse(contentDelta: '{"y":2}');
        })();

        iterator_to_array($gen->getPartialResponses($stream, $rm));

        $starts = array_values(array_filter($log, fn($e) => $e[0] === StreamedToolCallStarted::class));
        $completes = array_values(array_filter($log, fn($e) => $e[0] === StreamedToolCallCompleted::class));
        expect(count($starts))->toBeGreaterThanOrEqual(2);
        expect(count($completes))->toBeGreaterThanOrEqual(1);
        // First started should be toolA
        expect($starts[0][1]['toolCall']['name'] ?? '')->toBe('toolA');
        // After name switch, a completion event for toolA should occur before final completion of toolB
        $firstCompleteName = $completes[0][1]['toolCall']['name'] ?? '';
        expect($firstCompleteName)->toBe('toolA');
    });

    it('emits multiple updates for multiple parsable deltas within same tool', function () {
        $events = Mockery::mock(EventDispatcher::class);
        $log = [];
        $events->shouldReceive('dispatch')->byDefault()->andReturnUsing(function($e) use (&$log) {
            $log[] = [$e::class, $e->data];
            return $e;
        });

        $cfg = new StructuredOutputConfig();
        $gen = new PartialsGenerator(new PG_FakeDeserializer($events, $cfg), new PG_FakeTransformer($events, $cfg), $events);
        $rm = pg_makeResponseModel();

        $stream = (function() {
            yield new PartialInferenceResponse(toolName: 'fn');
            yield new PartialInferenceResponse(contentDelta: '{"a":1}');
            yield new PartialInferenceResponse(contentDelta: '{"a":1,"b":2}');
        })();

        iterator_to_array($gen->getPartialResponses($stream, $rm));

        $updates = array_values(array_filter($log, fn($e) => $e[0] === StreamedToolCallUpdated::class));
        expect(count($updates))->toBeGreaterThanOrEqual(2);
    });

    it('finalizes last tool at stream end without subsequent toolName', function () {
        $events = Mockery::mock(EventDispatcher::class);
        $log = [];
        $events->shouldReceive('dispatch')->byDefault()->andReturnUsing(function($e) use (&$log) {
            $log[] = [$e::class, $e->data];
            return $e;
        });

        $cfg = new StructuredOutputConfig();
        $gen = new PartialsGenerator(new PG_FakeDeserializer($events, $cfg), new PG_FakeTransformer($events, $cfg), $events);
        $rm = pg_makeResponseModel();

        $stream = (function() {
            yield new PartialInferenceResponse(toolName: 'fn');
            yield new PartialInferenceResponse(contentDelta: '{"a":1');
            yield new PartialInferenceResponse(contentDelta: '}');
            // stream ends here, no new toolName
        })();

        iterator_to_array($gen->getPartialResponses($stream, $rm));

        $completes = array_values(array_filter($log, fn($e) => $e[0] === StreamedToolCallCompleted::class));
        expect(count($completes))->toBeGreaterThanOrEqual(1);
        $completed = $completes[count($completes)-1][1]['toolCall'] ?? [];
        expect($completed['name'] ?? '')->toBe('fn');
        expect(($completed['arguments']['a'] ?? null))->toBe(1);
    });
});
