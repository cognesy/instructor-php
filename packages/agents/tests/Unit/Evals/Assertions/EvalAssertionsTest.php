<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Evals\Assertions;

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Agents\Evals\AssertionSeverity;
use Cognesy\Agents\Evals\EvalContext;
use Cognesy\Agents\Evals\EvalCount;
use Cognesy\Agents\Evals\EvalMatch;
use Cognesy\Agents\Evals\EvalRequirementFailed;
use Cognesy\Agents\Evals\LocalAgentTarget;
use Cognesy\Agents\Events\AgentExecutionCompleted;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;

function assertionContext(string $reply = 'Verification required for order A1049'): EvalContext {
    $target = LocalAgentTarget::fromFactory(static fn () => AgentBuilder::base()
        ->withCapability(new UseDriver(FakeAgentDriver::fromResponses($reply)))
        ->build());
    $context = new EvalContext($target);
    $context->send('refund A1049');
    return $context;
}

it('collects failures and supports severity threshold and labels', function (): void {
    $t = assertionContext();
    $t->check('first', false)->soft()->atLeast(0.5)->label('quality');
    $t->check('second', false);

    $results = $t->assertions()->all();
    expect($results)->toHaveCount(2)
        ->and($results[0]->severity())->toBe(AssertionSeverity::Soft)
        ->and($results[0]->label())->toBe('quality')
        ->and($results[1]->passed())->toBeFalse();
});

it('fails fast only for require', function (): void {
    $t = assertionContext();
    expect(fn () => $t->require('precondition', false, 'missing fixture'))
        ->toThrow(EvalRequirementFailed::class, 'missing fixture')
        ->and($t->assertions()->count())->toBe(1);
});

it('matches partial structures regex predicates and counts', function (): void {
    expect(EvalMatch::partial(['order' => ['id' => 'A1049']])->matches(['order' => ['id' => 'A1049', 'status' => 'open']]))->toBeTrue()
        ->and(EvalMatch::regex('/1049/')->matches(['id' => 'A1049']))->toBeTrue()
        ->and(EvalMatch::satisfies(static fn (int $value): bool => $value > 2)->matches(3))->toBeTrue()
        ->and(EvalCount::between(1, 3)->matches(2))->toBeTrue();
});

it('asserts reply values run status and captured events', function (): void {
    $t = assertionContext();
    $t->succeeded();
    $t->messageIncludes('Verification');
    $t->outputMatches('/A1049/');
    $t->usedNoTools();
    $t->maxToolCalls(0);
    $t->noFailedActions();
    $t->event(AgentExecutionCompleted::class);
    $t->notCalledTool('refunds_issue');
    $t->expect($t->run()->reply())->includes('order')->similarity('Verification required for order A1049')->atLeast(0.9);

    foreach ($t->assertions() as $result) {
        expect($result->passed())->toBeTrue();
    }
});

it('scopes a trailing atLeast() to only the most recently recorded assertion in the chain', function (): void {
    // Both targets are a single-character edit away from $actual (63 chars),
    // so both similarity() scores land in [0.9, 1.0): close enough to pass
    // at atLeast(0.9), but NOT close enough to pass at the implicit default
    // threshold (passed() requires score >= 1.0 when no atLeast() was ever
    // applied). That makes the pass/fail outcome of EACH assertion depend on
    // whether the trailing atLeast(0.9) actually reached it - unlike a
    // boolean matcher such as includes(), whose score is always exactly 0
    // or 1 and so passes/fails identically whether its threshold is 0.9 or
    // the 1.0 default (the reason the older test above can't detect
    // mis-scoping).
    $actual = 'The quick brown fox jumps over the lazy dog and further padding';
    $firstTarget = 'The quack brown fox jumps over the lazy dog and further padding';
    $lastTarget = 'The quick brown fox jumps over the lazy dog and further padxing';

    $t = assertionContext();
    $t->expect($actual)->similarity($firstTarget)->similarity($lastTarget)->atLeast(0.9);

    $results = $t->assertions()->all();
    expect($results)->toHaveCount(2);

    $first = $results[0];
    $last = $results[1];

    // Sanity check on the fixture itself, independent of chain-scoping.
    expect($first->score())->toBeGreaterThanOrEqual(0.9)->toBeLessThan(1.0)
        ->and($last->score())->toBeGreaterThanOrEqual(0.9)->toBeLessThan(1.0);

    // The trailing atLeast(0.9) must bind only to the LAST-recorded
    // assertion ($this->last at call time), leaving the first assertion on
    // its implicit default threshold.
    //
    // - If atLeast() instead bound to the FIRST recorded assertion, $first
    //   would carry threshold 0.9 (passing) and $last would keep the
    //   default 1.0 (failing) - the exact opposite of every expectation
    //   below.
    // - If atLeast() bound to ALL recorded assertions, $first would also
    //   carry threshold 0.9 and pass, flipping the first two expectations
    //   below even though the last two would still hold.
    expect($first->threshold())->toBeNull()
        ->and($first->passed())->toBeFalse()
        ->and($last->threshold())->toBe(0.9)
        ->and($last->passed())->toBeTrue();
});

it('shares assertions and logs with fresh child sessions', function (): void {
    $target = LocalAgentTarget::fromFactory(static fn () => AgentBuilder::base()
        ->withCapability(new UseDriver(FakeAgentDriver::fromResponses('ok')))
        ->build());
    $t = new EvalContext($target);
    $child = $t->newSession();

    $child->send('isolated');
    $child->messageIncludes('ok');
    $child->log('child checked', ['session' => 'isolated']);

    expect($t->assertions()->count())->toBe(1)
        ->and($t->logs()->count())->toBe(1)
        ->and($t->logs()->all()[0]->context())->toBe(['session' => 'isolated'])
        ->and($t->run()->turns())->toBe(0);
});

it('asserts exact step count', function (): void {
    $target = LocalAgentTarget::fromFactory(static fn () => AgentBuilder::base()
        ->withCapability(new UseDriver(FakeAgentDriver::fromResponses('response 1', 'response 2')))
        ->build());
    $t = new EvalContext($target);
    $t->send('first');
    $t->send('second');

    $result = $t->stepCount(2);
    expect($result->result()->passed())->toBeTrue();
});

it('fails when step count does not match', function (): void {
    $target = LocalAgentTarget::fromFactory(static fn () => AgentBuilder::base()
        ->withCapability(new UseDriver(FakeAgentDriver::fromResponses('response 1', 'response 2')))
        ->build());
    $t = new EvalContext($target);
    $t->send('first');
    $t->send('second');

    $result = $t->stepCount(3);
    expect($result->result()->passed())->toBeFalse()
        ->and($result->result()->message())->toContain('expected 3 steps, got 2');
});

it('asserts maximum step count', function (): void {
    $target = LocalAgentTarget::fromFactory(static fn () => AgentBuilder::base()
        ->withCapability(new UseDriver(FakeAgentDriver::fromResponses('response 1', 'response 2')))
        ->build());
    $t = new EvalContext($target);
    $t->send('first');
    $t->send('second');

    $result = $t->maxSteps(3);
    expect($result->result()->passed())->toBeTrue();
});

it('fails when step count exceeds maximum', function (): void {
    $target = LocalAgentTarget::fromFactory(static fn () => AgentBuilder::base()
        ->withCapability(new UseDriver(FakeAgentDriver::fromResponses('response 1', 'response 2', 'response 3')))
        ->build());
    $t = new EvalContext($target);
    $t->send('first');
    $t->send('second');
    $t->send('third');

    $result = $t->maxSteps(2);
    expect($result->result()->passed())->toBeFalse()
        ->and($result->result()->message())->toContain('expected at most 2 steps, got 3');
});

it('asserts total tokens at most limit', function (): void {
    $target = LocalAgentTarget::fromFactory(static fn () => AgentBuilder::base()
        ->withCapability(new UseDriver(FakeAgentDriver::fromSteps(
            ScenarioStep::final('response', new InferenceUsage(100, 50)),
        )))
        ->build());
    $t = new EvalContext($target);
    $t->send('test');

    $result = $t->totalTokensAtMost(200);
    expect($result->result()->passed())->toBeTrue();
});

it('fails when total tokens exceed limit', function (): void {
    $target = LocalAgentTarget::fromFactory(static fn () => AgentBuilder::base()
        ->withCapability(new UseDriver(FakeAgentDriver::fromSteps(
            ScenarioStep::final('response', new InferenceUsage(100, 150)),
        )))
        ->build());
    $t = new EvalContext($target);
    $t->send('test');

    $result = $t->totalTokensAtMost(200);
    expect($result->result()->passed())->toBeFalse()
        ->and($result->result()->message())->toContain('used 250 tokens, limit 200');
});
