<?php declare(strict_types=1);

use Cognesy\Addons\Chat\Collections\Participants;
use Cognesy\Addons\Chat\Contracts\CanParticipateInChat;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\ChatStep;
use Cognesy\Addons\Chat\Selectors\LLMBasedCoordinator;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\LLMProvider;
use Tests\Addons\Support\FakeInferenceDriver;

it('LLMBasedCoordinator selects participant by LLM response', function () {
    $p1 = new class implements CanParticipateInChat {
        public function name(): string { return 'user'; }
        public function act(ChatState $state): ChatStep { return new ChatStep('user'); }
    };
    $p2 = new class implements CanParticipateInChat {
        public function name(): string { return 'assistant'; }
        public function act(ChatState $state): ChatStep { return new ChatStep('assistant'); }
    };
    
    $participants = new Participants($p1, $p2);
    $state = new ChatState();

    $driver = new FakeInferenceDriver([
        new InferenceResponse(content: '{"participantName": "assistant", "reason": "Assistant should respond"}'),
    ]);
    $structuredOutput = (new StructuredOutput())
        ->withLLMProvider(LLMProvider::new()->withDriver($driver));
    
    $selector = new LLMBasedCoordinator(structuredOutput: $structuredOutput);
    $choice = $selector->nextParticipant($state, $participants);
    expect($choice?->name())->toBe('assistant');
});
