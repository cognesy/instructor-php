<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Evals\Judge;

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Evals\AssertionSeverity;
use Cognesy\Agents\Evals\EvalContext;
use Cognesy\Agents\Evals\FakeAgentJudge;
use Cognesy\Agents\Evals\JudgeEvidence;
use Cognesy\Agents\Evals\JudgeRequest;
use Cognesy\Agents\Evals\JudgeScore;
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

it('invokes the judge exactly once for a judge()->on()->atLeast() chain', function (): void {
    $calls = 0;
    $judge = FakeAgentJudge::fromClosure(function (JudgeRequest $request) use (&$calls): JudgeScore {
        $calls++;
        return new JudgeScore(0.9, 'reviewed');
    });
    $t = new EvalContext(judgeTarget(), judge: $judge);
    $t->send('refund');
    $t->judge()
        ->closedQa('Does it avoid issuing a refund?')
        ->on('Refund denied pending verification.')
        ->atLeast(0.75);

    $result = $t->assertions()->all()[0];

    expect($calls)->toBe(1)
        ->and($result->passed())->toBeTrue()
        ->and($result->score())->toBe(0.9);
});

it('never invokes the judge when the expectation result is never read', function (): void {
    $calls = 0;
    $judge = FakeAgentJudge::fromClosure(function (JudgeRequest $request) use (&$calls): JudgeScore {
        $calls++;
        return new JudgeScore(0.9, 'reviewed');
    });
    $t = new EvalContext(judgeTarget(), judge: $judge);
    $t->send('refund');
    $t->judge()->closedQa('Does it avoid issuing a refund?')->atLeast(0.75);

    expect($calls)->toBe(0);
});

it('memoizes the judge result across repeated reads', function (): void {
    $calls = 0;
    $judge = FakeAgentJudge::fromClosure(function (JudgeRequest $request) use (&$calls): JudgeScore {
        $calls++;
        return new JudgeScore(0.9, 'reviewed');
    });
    $t = new EvalContext(judgeTarget(), judge: $judge);
    $t->send('refund');
    $t->judge()->closedQa('Does it avoid issuing a refund?')->atLeast(0.75);

    $first = $t->assertions()->all()[0];
    $second = $t->assertions()->all()[0];

    expect($calls)->toBe(1)
        ->and($first->score())->toBe($second->score());
});

it('on() replaces only the graded output and retains the target run', function (): void {
    /** @var list<JudgeRequest> $seen */
    $seen = [];
    $judge = FakeAgentJudge::fromClosure(function (JudgeRequest $request) use (&$seen): JudgeScore {
        $seen[] = $request;
        return new JudgeScore(0.9, 'reviewed');
    });
    $t = new EvalContext(judgeTarget(), judge: $judge);
    $t->send('refund');
    $run = $t->run();
    $t->judge()
        ->closedQa('Does it avoid issuing a refund?')
        ->on('Refund denied pending verification.')
        ->atLeast(0.75);
    $t->assertions()->all();

    expect($seen)->toHaveCount(1)
        ->and($seen[0]->output)->toBe('Refund denied pending verification.')
        ->and($seen[0]->run)->toBe($run);
});

it('turns a judge exception into a gating failure carrying the exception message', function (): void {
    $judge = FakeAgentJudge::fromClosure(static fn (JudgeRequest $request) => throw new RuntimeException('offline'));
    $t = new EvalContext(judgeTarget(), judge: $judge);
    $t->send('refund');
    // soft() is explicitly called and must still be overridden by the exception path.
    $t->judge()->closedQa('safe?')->soft()->atLeast(0.9);

    $result = $t->assertions()->all()[0];

    expect($result->severity())->toBe(AssertionSeverity::Gate)
        ->and($result->message())->toContain('offline')
        ->and($result->passed())->toBeFalse();
});

it('serializes concise judge metadata without embedding a full judge trace', function (): void {
    $evidence = JudgeEvidence::of('reply avoids issuing a refund', 'no refund tool was called');
    $judge = FakeAgentJudge::fromClosure(static fn (JudgeRequest $request): JudgeScore
        => new JudgeScore(0.9, 'safe reply', $evidence));
    $t = new EvalContext(judgeTarget(), judge: $judge);
    $t->send('refund');
    $t->judge()->closedQa('Does it avoid issuing a refund?')->atLeast(0.75);

    $array = $t->assertions()->all()[0]->toArray();

    expect($array['judge'])->toBe([
        'score' => 0.9,
        'reason' => 'safe reply',
        'evidenceCount' => 2,
    ]);
});
