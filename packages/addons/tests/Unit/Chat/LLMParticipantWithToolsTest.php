<?php declare(strict_types=1);

use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Events\ChatToolUseCompleted;
use Cognesy\Addons\Chat\Events\ChatToolUseStarted;
use Cognesy\Addons\Chat\Participants\LLMParticipantWithTools;
use Cognesy\Addons\ToolUse\Collections\Tools;
use Cognesy\Addons\ToolUse\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Addons\ToolUse\Tools\FunctionTool;
use Cognesy\Addons\ToolUse\ToolUseFactory;
use Cognesy\Events\EventBusResolver;
use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;
use Cognesy\Polyglot\Inference\LLMProvider;
use Tests\Addons\Support\FakeInferenceDriver;

require_once __DIR__ . '/../../Support/FakeInferenceDriver.php';

function test_add(int $a, int $b): int {
    return $a + $b;
}

function test_multiply(int $a, int $b): int {
    return $a * $b;
}

it('creates participant with correct name and system prompt', function () {
    $toolUse = ToolUseFactory::default();
    $participant = new LLMParticipantWithTools(
        toolUse: $toolUse,
        name: 'test-assistant',
        systemPrompt: 'You are a helpful assistant.'
    );
    
    expect($participant->name())->toBe('test-assistant');
});

it('executes tool calls and returns chat step with tool results', function () {
    $driver = new FakeInferenceDriver([
        new InferenceResponse(
            content: '',
            toolCalls: new ToolCalls(new ToolCall('test_add', ['a' => 5, 'b' => 3])),
            usage: new Usage(10, 20),
            finishReason: 'tool_calls'
        ),
        new InferenceResponse(
            content: 'The result is 8.',
            toolCalls: new ToolCalls(),
            usage: new Usage(5, 15),
            finishReason: 'stop'
        )
    ]);

    $tools = new Tools(FunctionTool::fromCallable(test_add(...)));

    $toolUse = ToolUseFactory::default(
        tools: $tools,
        driver: new ToolCallingDriver(
            llm: LLMProvider::new()->withDriver($driver)
        )
    );

    $participant = new LLMParticipantWithTools(
        name: 'math-assistant',
        toolUse: $toolUse,
    );

    $messages = Messages::fromArray([
        ['role' => 'user', 'content' => 'Add 5 and 3']
    ]);
    $state = new ChatState(store: MessageStore::fromMessages($messages));

    $step = $participant->act($state);

    expect($step->participantName())->toBe('math-assistant');
    expect($step->outputMessages()->last()->toString())->toBe('The result is 8.');
    expect($step->usage()->total())->toBe(20); // final step usage only
    expect($step->finishReason())->toBe(InferenceFinishReason::Stop);
    expect($step->metadata()->toArray()['hasToolCalls'])->toBeFalse(); // final step doesn't have tool calls
    expect($step->metadata()->toArray()['toolsUsed'])->toBe(''); // final step doesn't have tool calls
    expect($step->metadata()->toArray()['toolErrors'])->toBe(0);
});

it('handles multiple tool calls in sequence', function () {
    $driver = new FakeInferenceDriver([
        new InferenceResponse(
            content: '',
            toolCalls: new ToolCalls(
                new ToolCall('test_add', ['a' => 5, 'b' => 3]),
                new ToolCall('test_multiply', ['a' => 2, 'b' => 4])
            ),
            usage: new Usage(15, 25),
            finishReason: 'tool_calls'
        ),
        new InferenceResponse(
            content: 'First result is 8, second result is 8.',
            toolCalls: new ToolCalls(),
            usage: new Usage(10, 20),
            finishReason: 'stop'
        )
    ]);

    $tools = new Tools(
        FunctionTool::fromCallable(test_add(...)),
        FunctionTool::fromCallable(test_multiply(...)),
    );

    $toolUse = ToolUseFactory::default(
        tools: $tools,
        driver: new ToolCallingDriver(
            llm: LLMProvider::new()->withDriver($driver)
        )
    );

    $participant = new LLMParticipantWithTools(
        toolUse: $toolUse,
        name: 'multi-tool-assistant'
    );

    $messages = Messages::fromArray([
        ['role' => 'user', 'content' => 'Add 5 and 3, then multiply 2 and 4']
    ]);
    $state = new ChatState(store: MessageStore::fromMessages($messages));

    $step = $participant->act($state);

    expect($step->participantName())->toBe('multi-tool-assistant');
    expect($step->outputMessages()->last()->toString())->toBe('First result is 8, second result is 8.');
    expect($step->usage()->total())->toBe(30); // final step usage only
    expect($step->metadata()->toArray()['toolsUsed'])->toBe(''); // final step doesn't have tool calls
});

it('prepends system prompt when provided', function () {
    $driver = new FakeInferenceDriver([
        new InferenceResponse(
            content: 'Hello! I am a helpful math assistant.',
            finishReason: 'stop',
            toolCalls: new ToolCalls(),
            usage: new Usage(5, 10),
        )
    ]);

    $toolUse = ToolUseFactory::default(
        tools: new Tools(),
        driver: new ToolCallingDriver(
            llm: LLMProvider::new()->withDriver($driver)
        )
    );

    $participant = new LLMParticipantWithTools(
        toolUse: $toolUse,
        name: 'system-assistant',
        systemPrompt: 'You are a helpful math assistant.'
    );

    $messages = Messages::fromArray([
        ['role' => 'user', 'content' => 'Hello']
    ]);
    $state = new ChatState(store: MessageStore::fromMessages($messages));

    $step = $participant->act($state);

    expect($step->inputMessages()->count())->toBe(2); // system + user message
    expect($step->inputMessages()->first()->role()->value)->toBe('system');
    expect($step->inputMessages()->first()->content()->toString())->toBe('You are a helpful math assistant.');
});

it('works without system prompt', function () {
    $driver = new FakeInferenceDriver([
        new InferenceResponse(
            content: 'Hello!',
            toolCalls: new ToolCalls(),
            usage: new Usage(3, 7),
            finishReason: 'stop'
        )
    ]);

    $toolUse = ToolUseFactory::default(
        tools: new Tools(),
        driver: new ToolCallingDriver(
            llm: LLMProvider::new()->withDriver($driver)
        )
    );

    $participant = new LLMParticipantWithTools(
        toolUse: $toolUse,
        name: 'basic-assistant'
    );

    $messages = Messages::fromArray([
        ['role' => 'user', 'content' => 'Hello']
    ]);
    $state = new ChatState(store: MessageStore::fromMessages($messages));

    $step = $participant->act($state);

    expect($step->inputMessages()->count())->toBe(1); // only user message
    expect($step->inputMessages()->first()->role()->value)->toBe('user');
});

it('dispatches tool use events', function () {
    $events = EventBusResolver::using(null);
    $startedEvents = [];
    $completedEvents = [];

    $events->addListener(ChatToolUseStarted::class, function($event) use (&$startedEvents) {
        $startedEvents[] = $event;
    });

    $events->addListener(ChatToolUseCompleted::class, function($event) use (&$completedEvents) {
        $completedEvents[] = $event;
    });

    $driver = new FakeInferenceDriver([
        new InferenceResponse(
            content: 'Result is ready.',
            toolCalls: new ToolCalls(),
            usage: new Usage(8, 12),
            finishReason: 'stop'
        )
    ]);

    $toolUse = ToolUseFactory::default(
        tools: new Tools(),
        driver: new ToolCallingDriver(
            llm: LLMProvider::new()->withDriver($driver)
        )
    );

    $participant = new LLMParticipantWithTools(
        toolUse: $toolUse,
        name: 'event-assistant',
        events: $events
    );

    $messages = Messages::fromArray([
        ['role' => 'user', 'content' => 'Test message']
    ]);
    $state = new ChatState(store: MessageStore::fromMessages($messages));

    $participant->act($state);

    expect($startedEvents)->toHaveCount(1);
    expect($completedEvents)->toHaveCount(1);
    
    $startedEvent = $startedEvents[0];
    expect($startedEvent->data['participant'])->toBe('event-assistant');
    expect($startedEvent->data['messages'])->toBeArray();
    expect($startedEvent->data['tools'])->toBeArray();
    
    $completedEvent = $completedEvents[0];
    expect($completedEvent->data['participant'])->toBe('event-assistant');
    expect($completedEvent->data['response'])->toBeString();
});
