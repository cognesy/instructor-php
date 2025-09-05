<?php declare(strict_types=1);

use Cognesy\Addons\Chat\Participants\HumanParticipant;
use Cognesy\Addons\Chat\Participants\LLMParticipant;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Template\Script\Script;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Tests\Addons\Support\FakeInferenceDriver;



it('human participant uses callback to provide messages', function () {
    $p = new HumanParticipant(id: 'user', messageProvider: fn() => 'hello');
    $step = $p->act(new ChatState(new Script()));
    expect($step->participantId())->toBe('user');
    expect($step->messages()->toArray()[0]['content'])->toBe('hello');
    expect($step->messages()->toArray()[0]['role'])->toBe('user');
});

it('llm participant uses provided inference driver', function () {
    $driver = new FakeInferenceDriver([
        new InferenceResponse(content: 'hi there!'),
    ]);
    $inference = (new Inference())->withLLMProvider(\Cognesy\Polyglot\Inference\LLMProvider::new()->withDriver($driver));
    $p = new LLMParticipant(id: 'assistant', inference: $inference, model: 'fake');
    $state = new ChatState((new Script())->withSection('main')->withSectionMessages('main', \Cognesy\Messages\Messages::fromString('hello')));
    $step = $p->act($state);
    $arr = $step->messages()->toArray();
    expect($arr[0]['role'])->toBe('assistant');
    expect($arr[0]['content'])->toBe('hi there!');
});
