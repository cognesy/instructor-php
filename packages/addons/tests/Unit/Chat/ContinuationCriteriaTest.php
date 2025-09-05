<?php declare(strict_types=1);

use Cognesy\Addons\Chat\ContinuationCriteria\StepsLimit;
use Cognesy\Addons\Chat\ContinuationCriteria\TokenUsageLimit;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Messages\Script\Script;
use Cognesy\Polyglot\Inference\Data\Usage;

it('stops when steps limit reached', function () {
    $state = new ChatState(new Script());
    $crit = new StepsLimit(2);
    expect($crit->canContinue($state))->toBeTrue();
    // simulate two steps
    $state = $state->withAddedStep(new \Cognesy\Addons\Chat\Data\ChatStep('a'));
    $state = $state->withAddedStep(new \Cognesy\Addons\Chat\Data\ChatStep('b'));
    expect($crit->canContinue($state))->toBeFalse();
});

it('stops when token usage exceeds limit', function () {
    $state = new ChatState(new Script());
    $state = $state->accumulateUsage(new Usage(inputTokens: 100, outputTokens: 50));
    $crit = new TokenUsageLimit(120);
    expect($crit->canContinue($state))->toBeFalse();
});

