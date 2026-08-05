<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Evals\Assertions;

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Evals\AssertionSeverity;
use Cognesy\Agents\Evals\EvalContext;
use Cognesy\Agents\Evals\EvalCount;
use Cognesy\Agents\Evals\EvalMatch;
use Cognesy\Agents\Evals\EvalRequirementFailed;
use Cognesy\Agents\Evals\LocalAgentTarget;
use Cognesy\Agents\Events\AgentExecutionCompleted;

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
