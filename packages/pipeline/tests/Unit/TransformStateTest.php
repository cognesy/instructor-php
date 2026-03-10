<?php declare(strict_types=1);

use Cognesy\Pipeline\ProcessingState;
use Cognesy\Pipeline\TransformState;
use Cognesy\Utils\TagMap\Contracts\TagInterface;

final readonly class TransformTestTag implements TagInterface
{
    public function __construct(public string $name) {}
}

it('maps successful values', function () {
    $transform = TransformState::with(ProcessingState::with(10))
        ->map(fn(int $value): int => $value * 2);

    expect($transform->isSuccess())->toBeTrue()
        ->and($transform->value())->toBe(20);
});

it('merges tags when a transform returns a new state', function () {
    $transform = TransformState::with(ProcessingState::with(10, [new TransformTestTag('initial')]))
        ->map(fn(int $value): ProcessingState => ProcessingState::with($value + 5, [new TransformTestTag('mapped')]));

    $tags = $transform->state()->allTags(TransformTestTag::class);

    expect($transform->value())->toBe(15)
        ->and($tags)->toHaveCount(2)
        ->and($tags[0]->name)->toBe('initial')
        ->and($tags[1]->name)->toBe('mapped');
});

it('can recover failed states with a fallback callback', function () {
    $recovered = TransformState::with(ProcessingState::with('test')->failWith('broken'))
        ->recoverWith(fn(): string => 'fallback');

    expect($recovered->isSuccess())->toBeTrue()
        ->and($recovered->value())->toBe('fallback');
});
