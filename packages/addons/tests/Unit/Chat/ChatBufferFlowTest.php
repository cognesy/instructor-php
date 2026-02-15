<?php declare(strict_types=1);

use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Events\ChatInferenceRequested;
use Cognesy\Addons\Chat\Participants\LLMParticipant;
use Cognesy\Addons\StepByStep\StateProcessing\Processors\MoveMessagesToBuffer;
use Cognesy\Addons\StepByStep\StateProcessing\StateProcessors;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Messages\MessageStore\Section;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Utils\Tokenizer;
use Tests\Addons\Support\FakeInferenceRequestDriver;

it('moves overflow messages into buffer while keeping recent messages', function () {
    $messages = Messages::fromString('First message.', 'user')
        ->appendMessage(Message::fromString('Second message.', 'assistant'))
        ->appendMessage(Message::fromString('Third message.', 'user'));

    $tokenLimit = Tokenizer::tokenCount('Third message.');

    $state = (new ChatState)->withMessages($messages);
    $processors = new StateProcessors(
        new MoveMessagesToBuffer(maxTokens: $tokenLimit, bufferSection: 'buffer'),
    );

    $updated = $processors->apply($state);

    expect(trim($updated->messages()->toString()))->toBe('Third message.');
    expect(trim($updated->store()->section('buffer')->messages()->toString()))
        ->toBe("First message.\nSecond message.");
});

it('compiles messages in summary-buffer-messages order with system prompt first', function () {
    $events = new EventDispatcher();
    $captured = null;
    $events->addListener(ChatInferenceRequested::class, function (ChatInferenceRequested $event) use (&$captured): void {
        $captured = $event->data['messages'] ?? null;
    });

    $driver = new FakeInferenceRequestDriver([new InferenceResponse(content: 'ok')]);
    $inference = (new Inference())->withLLMProvider(LLMProvider::new()->withDriver($driver));
    $participant = new LLMParticipant(
        name: 'expert',
        systemPrompt: 'SYS',
        inference: $inference,
        events: $events,
    );

    $store = MessageStore::fromSections(
        new Section('summary', Messages::fromString('SUMMARY', 'system')),
        new Section('buffer', Messages::fromString('BUFFER', 'user')),
        new Section('messages', Messages::fromString('RECENT', 'user')),
    );
    $state = new ChatState(store: $store);

    $participant->act($state);

    $compiled = Messages::fromArray($captured ?? []);
    expect(trim($compiled->toString()))->toBe("SYS\nSUMMARY\nBUFFER\nRECENT");
});
