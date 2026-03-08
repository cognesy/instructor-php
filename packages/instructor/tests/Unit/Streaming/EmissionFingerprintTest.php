<?php declare(strict_types=1);

use Cognesy\Instructor\Streaming\EmissionSnapshot;
use Cognesy\Instructor\Streaming\EmissionFingerprint;
use Cognesy\Instructor\Streaming\StructuredOutputStreamState;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Instructor\Enums\OutputMode;

it('tracks content and value changes for json emissions', function () {
    $fingerprint = EmissionFingerprint::fresh();

    $first = stateWith(
        new PartialInferenceDelta(contentDelta: '{"name":"Ann"'),
    );
    $sameSnapshot = stateWith(
        new PartialInferenceDelta(contentDelta: '{"name":"Ann"'),
    );
    $valued = stateWith(
        new PartialInferenceDelta(contentDelta: '{"name":"Ann"'),
        (object) ['name' => 'Ann'],
    );

    expect($fingerprint->hasChanged(EmissionSnapshot::fromState($first), OutputMode::Json))->toBeTrue();

    $fingerprint->remember(EmissionSnapshot::fromState($first), OutputMode::Json);

    expect($fingerprint->hasChanged(EmissionSnapshot::fromState($sameSnapshot), OutputMode::Json))->toBeFalse();
    expect($fingerprint->hasChanged(EmissionSnapshot::fromState($valued), OutputMode::Json))->toBeTrue();
});

it('tracks tool snapshot changes independently in tools mode', function () {
    $fingerprint = EmissionFingerprint::fresh();

    $first = stateWith(
        new PartialInferenceDelta(toolId: 'tool-1', toolName: 'extract_data', toolArgs: '{"name":"Ann"'),
    );
    $second = stateWith(
        new PartialInferenceDelta(toolId: 'tool-1', toolName: 'extract_data', toolArgs: '{"name":"Ann"'),
        null,
        new PartialInferenceDelta(toolId: 'tool-1', toolName: 'extract_data', toolArgs: ',"age":30}'),
    );
    $sameSnapshot = stateWith(
        new PartialInferenceDelta(toolId: 'tool-1', toolName: 'extract_data', toolArgs: '{"name":"Ann","age":30}'),
    );

    expect($fingerprint->hasChanged(EmissionSnapshot::fromState($first), OutputMode::Tools))->toBeTrue();

    $fingerprint->remember(EmissionSnapshot::fromState($first), OutputMode::Tools);

    expect($fingerprint->hasChanged(EmissionSnapshot::fromState($second), OutputMode::Tools))->toBeTrue();

    $fingerprint->remember(EmissionSnapshot::fromState($second), OutputMode::Tools);

    expect($fingerprint->hasChanged(EmissionSnapshot::fromState($sameSnapshot), OutputMode::Tools))->toBeFalse();
});

function stateWith(
    PartialInferenceDelta $first,
    mixed $value = null,
    ?PartialInferenceDelta $second = null,
): StructuredOutputStreamState {
    $state = StructuredOutputStreamState::empty();
    $state->applyDelta($first);

    if ($second !== null) {
        $state->applyDelta($second);
    }

    if ($value !== null) {
        $state->setValue($value);
    }

    return $state;
}
