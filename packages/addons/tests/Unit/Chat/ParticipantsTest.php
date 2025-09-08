<?php declare(strict_types=1);

use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Participants\ExternalParticipant;
use Cognesy\Addons\Chat\Participants\LLMParticipant;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Inference;
use Tests\Addons\Support\FakeInferenceDriver;

it('external participant uses provider to generate messages', function () {
    $provider = fn() => new Message(role: 'user', content: 'hello');
    $p = new ExternalParticipant(name: 'user', provider: $provider);
    $step = $p->act(new ChatState());
    expect($step->participantName())->toBe('user');
    expect($step->outputMessage()->content()->toString())->toBe('hello');
    expect($step->outputMessage()->role()->value)->toBe('user');
});

it('llm participant uses provided inference driver', function () {
    $driver = new FakeInferenceDriver([
        new InferenceResponse(content: 'hi there!'),
    ]);
    $inference = (new Inference())->withLLMProvider(\Cognesy\Polyglot\Inference\LLMProvider::new()->withDriver($driver));
    
    $p = new LLMParticipant(name: 'assistant', inference: $inference);
    
    $messages = Messages::fromArray([
        ['role' => 'user', 'content' => 'hello']
    ]);
    $state = new ChatState(store: MessageStore::fromMessages($messages));
    
    $step = $p->act($state);
    
    expect($step->participantName())->toBe('assistant');
    expect($step->outputMessage()->content()->toString())->toBe('hi there!');
    expect($step->outputMessage()->role()->value)->toBe('assistant');
});
