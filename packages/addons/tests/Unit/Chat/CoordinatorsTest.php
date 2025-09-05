<?php declare(strict_types=1);

use Cognesy\Addons\Chat\Contracts\CanParticipateInChat;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\ChatStep;
use Cognesy\Addons\Chat\Selectors\LLMBasedCoordinator;
use Cognesy\Addons\Chat\Selectors\ToolBasedCoordinator;
use Cognesy\Addons\ToolUse\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Addons\ToolUse\ToolUse;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Inference;
use Tests\Addons\Support\FakeInferenceDriver;

it('LLMBasedCoordinator selects participant by LLM response id', function () {
    $p1 = new class implements CanParticipateInChat {
        public function id(): string { return 'user'; }
        public function act(ChatState $state): ChatStep { return new ChatStep('user'); }
    };
    $p2 = new class implements CanParticipateInChat {
        public function id(): string { return 'assistant'; }
        public function act(ChatState $state): ChatStep { return new ChatStep('assistant'); }
    };
    $state = new ChatState();
    $state = $state->withParticipants($p1, $p2);

    $driver = new FakeInferenceDriver([
        new InferenceResponse(content: 'assistant'),
    ]);
    $inference = (new Inference())->withLLMProvider(\Cognesy\Polyglot\Inference\LLMProvider::new()->withDriver($driver));
    $selector = new LLMBasedCoordinator(inference: $inference);
    $choice = $selector->choose($state);
    expect($choice?->id())->toBe('assistant');
});

it('ToolBasedCoordinator selects participant by ToolUse final response id', function () {
    $p1 = new class implements CanParticipateInChat {
        public function id(): string { return 'user'; }
        public function act(ChatState $state): ChatStep { return new ChatStep('user'); }
    };
    $p2 = new class implements CanParticipateInChat {
        public function id(): string { return 'assistant'; }
        public function act(ChatState $state): ChatStep { return new ChatStep('assistant'); }
    };
    $state = new ChatState();
    $state = $state->withParticipants($p1, $p2);

    $driver = new FakeInferenceDriver([
        new InferenceResponse(content: 'user'),
    ]);
    $toolUse = (new ToolUse)
        ->withDriver(new ToolCallingDriver(llm: \Cognesy\Polyglot\Inference\LLMProvider::new()->withDriver($driver)));
    $selector = new ToolBasedCoordinator(toolUse: $toolUse);
    $choice = $selector->choose($state);
    expect($choice?->id())->toBe('user');
});
