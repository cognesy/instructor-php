<?php declare(strict_types=1);

use Cognesy\Pipeline\Enums\ErrorStrategy;
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Pipeline\StateContracts\CanCarryState;
use Cognesy\Utils\Result\Result;
use Cognesy\Utils\TagMap\Contracts\TagInterface;

final readonly class PipelineTestTag implements TagInterface
{
    public function __construct(public string $name) {}
}

it('runs sequential steps and can be reused for different inputs', function () {
    $pending = Pipeline::builder()
        ->through(fn(int $value): int => $value * 2)
        ->through(fn(int $value): int => $value + 1)
        ->create()
        ->executeWith(ProcessingState::with(2));

    expect($pending->value())->toBe(5)
        ->and($pending->for(5)->value())->toBe(11);
});

it('normalizes Result and state outputs while preserving tags', function () {
    $pending = Pipeline::builder()
        ->through(fn(int $value): Result => Result::success($value * 2))
        ->through(fn(int $value): ProcessingState => ProcessingState::with($value + 1, [new PipelineTestTag('step')]))
        ->create()
        ->executeWith(ProcessingState::with(2, [new PipelineTestTag('initial')]));

    $state = $pending->state();
    $tags = $state->allTags(PipelineTestTag::class);

    expect($state->value())->toBe(5)
        ->and($tags)->toHaveCount(2)
        ->and($tags[0]->name)->toBe('initial')
        ->and($tags[1]->name)->toBe('step');
});

it('supports tap, when, and filter as value-oriented conveniences', function () {
    $seen = [];

    $pending = Pipeline::builder()
        ->through(fn(int $value): int => $value * 2)
        ->tap(function (int $value) use (&$seen): void {
            $seen[] = $value;
        })
        ->when(fn(int $value): bool => $value > 5, fn(int $value): int => $value + 10)
        ->filter(fn(int $value): bool => $value === 16, 'unexpected value')
        ->create()
        ->executeWith(ProcessingState::with(3));

    expect($pending->value())->toBe(16)
        ->and($seen)->toBe([6]);
});

it('runs failure handlers and lets finalizers shape the final result', function () {
    $failures = [];

    $result = Pipeline::builder()
        ->through(fn(string $value): string => strtoupper($value))
        ->through(fn(): never => throw new RuntimeException('boom'))
        ->onFailure(function (CanCarryState $state) use (&$failures): void {
            $failures[] = $state->exception()->getMessage();
        })
        ->finally(fn(CanCarryState $state): Result => match ($state->isSuccess()) {
            true => $state->result(),
            false => Result::failure('wrapped: ' . $state->exception()->getMessage()),
        })
        ->create()
        ->executeWith(ProcessingState::with('test'))
        ->result();

    expect($result->isFailure())->toBeTrue()
        ->and($result->exception()->getMessage())->toBe('wrapped: boom')
        ->and($failures)->toBe(['boom']);
});

it('fails when a value step returns null', function () {
    $pending = Pipeline::builder()
        ->through(fn(string $value): ?string => null)
        ->create()
        ->executeWith(ProcessingState::with('test'));

    expect($pending->isFailure())->toBeTrue()
        ->and($pending->exception()?->getMessage())->toBe('Null value encountered');
});

it('rethrows step exceptions in fail fast mode', function () {
    $pending = Pipeline::builder(ErrorStrategy::FailFast)
        ->through(fn(): never => throw new RuntimeException('stop immediately'))
        ->create()
        ->executeWith(ProcessingState::with('test'));

    expect(fn() => $pending->state())->toThrow(RuntimeException::class, 'stop immediately');
});
