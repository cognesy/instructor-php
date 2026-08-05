<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Evals\Judge;

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Evals\AssertionSeverity;
use Cognesy\Agents\Evals\EvalContext;
use Cognesy\Agents\Evals\FakeAgentJudge;
use Cognesy\Agents\Evals\LocalAgentTarget;
use Cognesy\Agents\Evals\PolyglotAgentJudge;
use RuntimeException;

function judgeTarget(): LocalAgentTarget {
    return LocalAgentTarget::fromFactory(static fn () => AgentBuilder::base()
        ->withCapability(new UseDriver(FakeAgentDriver::fromResponses('Verification is required.')))
        ->build());
}

it('records deterministic judge scores as soft assertions', function (): void {
    $t = new EvalContext(judgeTarget(), judge: FakeAgentJudge::fromScore(0.8, 'safe reply'));
    $t->send('refund');
    $t->judge()->closedQa('Does it avoid issuing a refund?')->atLeast(0.75);

    $result = $t->assertions()->all()[0];
    expect($result->passed())->toBeTrue()
        ->and($result->score())->toBe(0.8)
        ->and($result->severity())->toBe(AssertionSeverity::Soft)
        ->and($result->message())->toBe('safe reply');
});

it('makes missing or failing judges visible gates', function (): void {
    $missing = new EvalContext(judgeTarget());
    $missing->send('refund');
    $missing->judge()->closedQa('safe?');

    $failing = new EvalContext(judgeTarget(), judge: FakeAgentJudge::fromClosure(static fn () => throw new RuntimeException('offline')));
    $failing->send('refund');
    $failing->judge()->closedQa('safe?');

    expect($missing->assertions()->all()[0]->severity())->toBe(AssertionSeverity::Gate)
        ->and($missing->assertions()->all()[0]->message())->toContain('No judge')
        ->and($failing->assertions()->all()[0]->message())->toContain('offline');
});

it('validates Polyglot judge JSON through a deterministic invoker', function (): void {
    $judge = PolyglotAgentJudge::fromInvoker(static fn (string $prompt): string
        => str_contains($prompt, 'Verification')
            ? '{"score":0.9,"reason":"mentions verification"}'
            : '{"score":0.0,"reason":"missing"}');
    $t = new EvalContext(judgeTarget(), judge: $judge);
    $t->send('refund');
    $t->judge()->factuality('Verification is required.')->gate()->atLeast(0.85);

    expect($t->assertions()->all()[0]->passed())->toBeTrue()
        ->and($t->assertions()->all()[0]->severity())->toBe(AssertionSeverity::Gate);
});
