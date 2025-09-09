<?php declare(strict_types=1);

use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Data\ToolUseStep;
use Cognesy\Addons\ToolUse\Processors\AccumulateTokenUsage;
use Cognesy\Addons\ToolUse\Processors\AppendContextVariables;
use Cognesy\Addons\ToolUse\Processors\AppendToolStateMessages;
use Cognesy\Addons\ToolUse\Processors\UpdateToolState;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\Usage;

it('accumulates usage from step into state', function () {
    $state = new ToolUseState();
    $state = $state->accumulateUsage(new Usage(1,2));
    $step = new ToolUseStep(usage: new Usage(3,4));

    $p = new AccumulateTokenUsage();
    $state = $p->processStep($step, $state);

    expect($state->usage()->toArray())->toMatchArray(['input' => 4, 'output' => 6, 'cacheWrite' => 0, 'cacheRead' => 0, 'reasoning' => 0]);
});

it('appends context variables message when variables present', function () {
    $state = new ToolUseState();
    $state = $state->withVariable('a', 1);
    $before = $state->messages()->count();

    $step = new ToolUseStep();
    $state = (new AppendContextVariables())->processStep($step, $state);

    expect($state->messages()->count())->toBe($before + 1);
});

it('does not append context variables when none present', function () {
    $state = new ToolUseState();
    $before = $state->messages()->count();
    $step = new ToolUseStep();

    $state = (new AppendContextVariables())->processStep($step, $state);
    expect($state->messages()->count())->toBe($before);
});

it('appends step messages to state', function () {
    $state = new ToolUseState();
    $msgs = Messages::fromMessages([ new Message(role: 'user', content: 'x') ]);
    $step = new ToolUseStep(messages: $msgs);

    $state = (new AppendToolStateMessages())->processStep($step, $state);
    expect($state->messages()->count())->toBe(1);
});

it('updates current step and increments steps list', function () {
    $state = new ToolUseState();
    $step = new ToolUseStep();

    $state = (new UpdateToolState())->processStep($step, $state);
    expect($state->currentStep())->toBe($step);
    expect($state->stepCount())->toBe(1);
});

