<?php declare(strict_types=1);

use Cognesy\Pipeline\ProcessingState;
use Cognesy\Pipeline\TransformState;
use Cognesy\Utils\TagMap\Contracts\TagInterface;
use Cognesy\Utils\TagMap\Tags\ErrorTag;

final readonly class StateTestTag implements TagInterface
{
    public function __construct(public string $name) {}
}

it('merges prior tags when applying one state to another', function () {
    $prior = ProcessingState::with('prior', [new StateTestTag('prior')]);
    $next = ProcessingState::with('next', [new StateTestTag('next')]);

    $merged = $next->applyTo($prior);
    $tags = $merged->allTags(StateTestTag::class);

    expect($merged->value())->toBe('next')
        ->and($tags)->toHaveCount(2)
        ->and($tags[0]->name)->toBe('prior')
        ->and($tags[1]->name)->toBe('next');
});

it('records failures as result failures with error tags', function () {
    $state = ProcessingState::with('test')->failWith('bad state');

    expect($state->isFailure())->toBeTrue()
        ->and($state->exception()->getMessage())->toBe('bad state')
        ->and($state->hasTag(ErrorTag::class))->toBeTrue();
});

it('exposes transform helpers from the state', function () {
    $transform = ProcessingState::with(10)->transform();

    expect($transform)->toBeInstanceOf(TransformState::class)
        ->and($transform->map(fn(int $value): int => $value + 5)->value())->toBe(15);
});
