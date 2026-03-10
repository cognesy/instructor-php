<?php

use Cognesy\Polyglot\Inference\Data\ToolChoice;

it('creates named tool choice modes', function () {
    expect(ToolChoice::auto()->isAuto())->toBeTrue()
        ->and(ToolChoice::required()->isRequired())->toBeTrue()
        ->and(ToolChoice::none()->isNone())->toBeTrue()
        ->and(ToolChoice::empty()->isEmpty())->toBeTrue();
});

it('creates a specific tool choice', function () {
    $choice = ToolChoice::specific('search');

    expect($choice->isSpecific())->toBeTrue()
        ->and($choice->functionName())->toBe('search')
        ->and($choice->mode())->toBe('specific');
});

it('normalizes string tool choice formats', function () {
    expect(ToolChoice::fromAny('auto')->isAuto())->toBeTrue()
        ->and(ToolChoice::fromAny('required')->isRequired())->toBeTrue()
        ->and(ToolChoice::fromAny('none')->isNone())->toBeTrue()
        ->and(ToolChoice::fromAny('')->isEmpty())->toBeTrue();
});

it('normalizes array tool choice formats', function () {
    $specific = ToolChoice::fromAny(['function' => ['name' => 'search']]);
    $typedAuto = ToolChoice::fromAny(['type' => 'auto']);
    $typedSpecific = ToolChoice::fromAny([
        'type' => 'function',
        'function' => ['name' => 'lookup'],
    ]);

    expect($specific->isSpecific())->toBeTrue()
        ->and($specific->functionName())->toBe('search')
        ->and($typedAuto->isAuto())->toBeTrue()
        ->and($typedSpecific->isSpecific())->toBeTrue()
        ->and($typedSpecific->functionName())->toBe('lookup');
});

it('serializes tool choice back to current wire formats', function () {
    expect(ToolChoice::auto()->toArray())->toBe('auto')
        ->and(ToolChoice::required()->toArray())->toBe('required')
        ->and(ToolChoice::none()->toArray())->toBe('none')
        ->and(ToolChoice::empty()->toArray())->toBe([])
        ->and(ToolChoice::specific('search')->toArray())->toBe([
            'type' => 'function',
            'function' => ['name' => 'search'],
        ]);
});

it('round-trips specific tool choice through canonical serialization', function () {
    $choice = ToolChoice::fromAny(['function' => ['name' => 'search']]);

    expect(ToolChoice::fromAny($choice->toArray())->functionName())->toBe('search')
        ->and(ToolChoice::fromAny($choice->toArray())->isSpecific())->toBeTrue();
});
