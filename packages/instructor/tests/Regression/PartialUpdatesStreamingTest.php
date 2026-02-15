<?php declare(strict_types=1);

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Tests\Addons\Support\FakeInferenceRequestDriver;

class StreamUser
{
    public int $count;
    public function __construct(int $count) { $this->count = $count; }
}

it('calls onPartialUpdate for each partial value', function () {
    // Prepare a deterministic stream of partial responses with values already set
    $p1 = (new PartialInferenceResponse(contentDelta: ''))->withValue(new StreamUser(1));
    $p2 = (new PartialInferenceResponse(contentDelta: ''))->withValue(new StreamUser(2));
    $p3 = (new PartialInferenceResponse(contentDelta: ''))->withValue(new StreamUser(3));

    $driver = new FakeInferenceRequestDriver(
        responses: [],
        streamBatches: [[ $p1, $p2, $p3 ]],
    );

    $received = [];

    $so = (new StructuredOutput())
        ->withDriver($driver)
        ->withMessages('ignored for test')
        ->withResponseClass(StreamUser::class)
        ->withOutputMode(OutputMode::Json)
        ->withStreaming()
        ->onPartialUpdate(function ($partial) use (&$received) { $received[] = $partial; });

    // Consume the stream to trigger events
    $stream = $so->stream();
    foreach ($stream->responses() as $r) { /* consume */ }

    // Verify handler was called per each partial
    expect(count($received))->toBe(3);
    expect($received[0])->toBeInstanceOf(StreamUser::class);
    expect($received[1])->toBeInstanceOf(StreamUser::class);
    expect($received[2])->toBeInstanceOf(StreamUser::class);
    expect($received[0]->count)->toBe(1);
    expect($received[1]->count)->toBe(2);
    expect($received[2]->count)->toBe(3);
});
