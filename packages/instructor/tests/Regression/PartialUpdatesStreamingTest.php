<?php declare(strict_types=1);

use Cognesy\Instructor\Creation\StructuredOutputConfigBuilder;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Tests\Addons\Support\FakeInferenceDriver;

class StreamUser
{
    public int $count;
    public function __construct(int $count) { $this->count = $count; }
}

it('yields each partial value via stream partials()', function () {
    // Prepare a deterministic stream of partial responses with values already set
    $p1 = (new PartialInferenceResponse(contentDelta: ''))->withValue(new StreamUser(1));
    $p2 = (new PartialInferenceResponse(contentDelta: ''))->withValue(new StreamUser(2));
    $p3 = (new PartialInferenceResponse(contentDelta: ''))->withValue(new StreamUser(3));

    $driver = new FakeInferenceDriver(
        responses: [],
        streamBatches: [[ $p1, $p2, $p3 ]],
    );

    $received = [];

    $config = (new StructuredOutputConfigBuilder())
        ->withOutputMode(OutputMode::Json)
        ->create();
    $runtime = makeStructuredRuntime(
        driver: $driver,
        config: $config,
    );

    $so = (new StructuredOutput($runtime))
        ->withMessages('ignored for test')
        ->withResponseClass(StreamUser::class)
        ->withStreaming();

    // Consume stream partials and collect values.
    foreach ($so->stream()->partials() as $partial) {
        $received[] = $partial;
    }

    // Verify handler was called per each partial
    expect(count($received))->toBe(3);
    expect($received[0])->toBeInstanceOf(StreamUser::class);
    expect($received[1])->toBeInstanceOf(StreamUser::class);
    expect($received[2])->toBeInstanceOf(StreamUser::class);
    expect($received[0]->count)->toBe(1);
    expect($received[1]->count)->toBe(2);
    expect($received[2]->count)->toBe(3);
});
