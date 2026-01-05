<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Chat;

use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\ChatStep;
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
    expect($step->outputMessages()->last()->toString())->toBe('hello');
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
    expect($step->outputMessages()->last()->toString())->toBe('hi there!');
});

it('encapsulates compiled context and transcript in ChatStep collections', function () {
    $input = Messages::fromArray([
        ['role' => 'system', 'content' => 'You are a friendly assistant.'],
        ['role' => 'user', 'content' => 'Say hello to the world.'],
    ]);

    $output = Messages::fromArray([
        ['role' => 'assistant', 'content' => 'Hello, world!'],
    ]);

    $step = new ChatStep(
        participantName: 'assistant',
        inputMessages: $input,
        outputMessages: $output,
    );

    expect($step->inputMessages()->toArray())->toBe($input->toArray());
    expect($step->outputMessages()->toArray())->toBe($output->toArray());
    expect($step->outputMessages()->last()->role()->value)->toBe('assistant');
});
