<?php declare(strict_types=1);

use Cognesy\Addons\Chat\Chat;
use Cognesy\Addons\Chat\ContinuationCriteria\StepsLimit;
use Cognesy\Addons\Chat\Participants\HumanParticipant;
use Cognesy\Addons\Chat\Participants\LLMParticipant;
use Cognesy\Addons\Chat\Selectors\RoundRobinSelector;
use Cognesy\Messages\Script\Script;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Inference;
use Tests\Addons\Support\FakeInferenceDriver;

it('runs a two-turn human ⇄ llm conversation deterministically', function () {
    $script = (new Script())->withSection('summary')->withSection('buffer')->withSection('main');
    $state = new \Cognesy\Addons\Chat\Data\ChatState($script);

    $human = new HumanParticipant(id: 'user', messageProvider: fn() => 'Hello');

    $driver = new FakeInferenceDriver([
        new InferenceResponse(content: 'Hi!'),
    ]);
    $inference = (new Inference())->withLLMProvider(\Cognesy\Polyglot\Inference\LLMProvider::new()->withDriver($driver));
    $assistant = new LLMParticipant(id: 'assistant', inference: $inference, model: 'fake');

    $chat = (new Chat(state: $state, selector: new RoundRobinSelector(), continuationCriteria: [new StepsLimit(2)]))
        ->withParticipants([$human, $assistant]);

    $steps = iterator_to_array($chat->iterator());
    expect(count($steps))->toBe(2);

    $final = $chat->state()->script()->select(['main'])->toMessages()->toArray();
    expect($final[0]['role'])->toBe('user');
    expect($final[0]['content'])->toBe('Hello');
    expect($final[1]['role'])->toBe('assistant');
    expect($final[1]['content'])->toBe('Hi!');
});
