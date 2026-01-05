<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Chat;

use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\ChatStep;
use Cognesy\Addons\StepByStep\Continuation\CanDecideToContinue;
use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;
use Cognesy\Addons\StepByStep\Continuation\Criteria\StepsLimit;
use Cognesy\Addons\StepByStep\Continuation\Criteria\TokenUsageLimit;
use Cognesy\Polyglot\Inference\Data\Usage;

final class CriterionCounter
{
    public function __construct(public int $calls = 0) {}

    public function increment(): void
    {
        $this->calls++;
    }
}

final class CountingChatCriterion implements CanDecideToContinue
{
    public function __construct(
        private CriterionCounter $counter,
        private ContinuationDecision $result,
    ) {}

    #[\Override]
    public function decide(object $state): ContinuationDecision
    {
        $this->counter->increment();
        return $this->result;
    }
}

it('stops when steps limit reached', function () {
    $state = new ChatState();
    $crit = new StepsLimit(2, fn(ChatState $s) => $s->steps()->count());
    // Under limit - allow continuation (guard permits)
    expect($crit->decide($state))->toBe(ContinuationDecision::AllowContinuation);
    // simulate two steps
    $state = $state->withAddedStep(new ChatStep('participant-a'));
    $state = $state->withAddedStep(new ChatStep('participant-b'));
    // At limit - forbid continuation (guard denies)
    expect($crit->decide($state))->toBe(ContinuationDecision::ForbidContinuation);
});

it('stops when token usage exceeds limit', function () {
    $state = new ChatState();
    $state = $state->withAccumulatedUsage(new Usage(inputTokens: 100, outputTokens: 50));
    $crit = new TokenUsageLimit(120, fn(ChatState $s) => $s->usage()->total());
    // Over limit - forbid continuation
    expect($crit->decide($state))->toBe(ContinuationDecision::ForbidContinuation);
});

it('chat continuation criteria reports emptiness', function () {
    $criteria = new ContinuationCriteria();
    expect($criteria->isEmpty())->toBeTrue();
});

it('chat continuation criteria mutates when adding', function () {
    $counter = new CriterionCounter();
    $criteria = new ContinuationCriteria();
    $criteria = $criteria->withCriteria(new CountingChatCriterion($counter, ContinuationDecision::AllowStop));

    expect($criteria->isEmpty())->toBeFalse();
    // All AllowStop → AllowStop (no work requested)
    expect($criteria->decide(new ChatState()))->toBe(ContinuationDecision::AllowStop);
    expect($counter->calls)->toBe(1);
});

it('chat continuation criteria uses priority resolution', function () {
    $counter = new CriterionCounter();
    $criteria = new ContinuationCriteria();
    $criteria = $criteria->withCriteria(new CountingChatCriterion($counter, ContinuationDecision::ForbidContinuation));
    $criteria = $criteria->withCriteria(new CountingChatCriterion($counter, ContinuationDecision::RequestContinuation));

    // ForbidContinuation wins → AllowStop (resolution returns false)
    expect($criteria->decide(new ChatState()))->toBe(ContinuationDecision::AllowStop);
    // Both are evaluated (priority resolution, no short-circuit)
    expect($counter->calls)->toBe(2);
});
