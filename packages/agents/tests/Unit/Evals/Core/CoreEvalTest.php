<?php declare(strict_types=1);

use Cognesy\Agents\Evals\AgentEval;
use Cognesy\Agents\Evals\AssertionResult;
use Cognesy\Agents\Evals\AssertionResults;
use Cognesy\Agents\Evals\AssertionSeverity;
use Cognesy\Agents\Evals\EvalTags;
use Cognesy\Agents\Evals\EvalVerdict;
use Cognesy\Agents\Evals\EvalVerdictResolver;

it('defines an immutable eval without an authored id', function (): void {
    $eval = AgentEval::define('Checks an agent', static function (): void {}, EvalTags::of('smoke'));

    expect($eval->id())->toBeNull()
        ->and($eval->withId('support/refund')->id())->toBe('support/refund')
        ->and($eval->tags()->has('smoke'))->toBeTrue();
});

it('rejects invalid assertion scores and thresholds', function (): void {
    expect(fn () => new AssertionResult('invalid', 1.1))->toThrow(InvalidArgumentException::class)
        ->and(fn () => AssertionResult::pass('x')->withThreshold(-0.1))->toThrow(InvalidArgumentException::class);
});

it('resolves the complete verdict matrix', function (
    AssertionResults $results,
    bool $skipped,
    ?string $error,
    EvalVerdict $expected,
): void {
    expect((new EvalVerdictResolver())->resolve($results, $skipped, $error))->toBe($expected);
})->with([
    'passed' => [new AssertionResults(AssertionResult::pass('ok')), false, null, EvalVerdict::Passed],
    'failed gate' => [new AssertionResults(AssertionResult::fail('gate')), false, null, EvalVerdict::Failed],
    'scored' => [new AssertionResults(AssertionResult::fail('soft')->withSeverity(AssertionSeverity::Soft)), false, null, EvalVerdict::Scored],
    'skipped' => [AssertionResults::none(), true, null, EvalVerdict::Skipped],
    'error wins' => [AssertionResults::none(), true, 'boom', EvalVerdict::Failed],
]);
