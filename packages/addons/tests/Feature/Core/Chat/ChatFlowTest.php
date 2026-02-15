<?php declare(strict_types=1);

use Cognesy\Addons\Chat\ChatFactory;
use Cognesy\Addons\Chat\Collections\Participants;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Participants\ExternalParticipant;
use Cognesy\Addons\Chat\Participants\LLMParticipant;
use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\Continuation\Criteria\StepsLimit;
use Cognesy\Messages\Message;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Inference;
use Tests\Addons\Support\FakeInferenceRequestDriver;

it('runs a two-turn human ⇄ llm conversation deterministically', function () {
    $human = new ExternalParticipant(
        name: 'user', 
        provider: fn() => new Message(role: 'user', content: 'Hello')
    );

    $driver = new FakeInferenceRequestDriver([
        new InferenceResponse(content: 'Hi!'),
    ]);
    $inference = (new Inference())->withLLMProvider(\Cognesy\Polyglot\Inference\LLMProvider::new()->withDriver($driver));
    $assistant = new LLMParticipant(name: 'assistant', inference: $inference);

    $participants = new Participants($human, $assistant);
    $continuationCriteria = new ContinuationCriteria(new StepsLimit(2, fn(ChatState $state) => $state->stepCount()));
    
    $chat = ChatFactory::default(
        participants: $participants,
        continuationCriteria: $continuationCriteria
    );
    
    $state = new ChatState();

    $state1 = $chat->nextStep($state);
    $state2 = $chat->nextStep($state1);

    $final = $state2->messages()->toArray();
    expect(count($final))->toBe(2);
    expect($final[0]['role'])->toBe('user');
    expect($final[0]['content'])->toBe('Hello');
    expect($final[1]['role'])->toBe('assistant');
    expect($final[1]['content'])->toBe('Hi!');
});
