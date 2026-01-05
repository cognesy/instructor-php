<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Chat;

use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\ChatStep;
use Cognesy\Addons\StepByStep\Continuation\CanDecideToContinue;
use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
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
        private bool $result,
    ) {}

    public function canContinue(object $state): bool
    {
        $this->counter->increment();
        return $this->result;
    }
}

it('stops when steps limit reached', function () {
    $state = new ChatState();
    $crit = new StepsLimit(2, fn(ChatState $s) => $s->steps()->count());
    expect($crit->canContinue($state))->toBeTrue();
    // simulate two steps
    $state = $state->withAddedStep(new ChatStep('participant-a'));
    $state = $state->withAddedStep(new ChatStep('participant-b'));
    expect($crit->canContinue($state))->toBeFalse();
});

it('stops when token usage exceeds limit', function () {
    $state = new ChatState();
    $state = $state->withAccumulatedUsage(new Usage(inputTokens: 100, outputTokens: 50));
    $crit = new TokenUsageLimit(120, fn(ChatState $s) => $s->usage()->total());
    expect($crit->canContinue($state))->toBeFalse();
});

it('chat continuation criteria reports emptiness', function () {
    $criteria = new ContinuationCriteria();
    expect($criteria->isEmpty())->toBeTrue();
});

it('chat continuation criteria mutates when adding', function () {
    $counter = new CriterionCounter();
    $criteria = new ContinuationCriteria();
    $criteria = $criteria->withCriteria(new CountingChatCriterion($counter, false));

    expect($criteria->isEmpty())->toBeFalse();
    expect($criteria->canContinue(new ChatState()))->toBeFalse();
    expect($counter->calls)->toBe(1);
});

it('chat continuation criteria short circuits on failure', function () {
    $counter = new CriterionCounter();
    $criteria = new ContinuationCriteria();
    $criteria = $criteria->withCriteria(new CountingChatCriterion($counter, false));
    $criteria = $criteria->withCriteria(new CountingChatCriterion($counter, true));

    expect($criteria->canContinue(new ChatState()))->toBeFalse();
    expect($counter->calls)->toBe(1);
});
