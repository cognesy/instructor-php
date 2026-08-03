<?php declare(strict_types=1);

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Capability\Summarization\Contracts\CanSummarizeMessages;
use Cognesy\Agents\Capability\Summarization\MoveMessagesToBufferHook;
use Cognesy\Agents\Capability\Summarization\SummarizationPolicy;
use Cognesy\Agents\Capability\Summarization\UseSummarization;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Agents\Hook\Data\HookContext;
use Cognesy\Agents\Hook\Enums\HookTrigger;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Utils\Tokenization\Contracts\CanCountTokens;
use Cognesy\Utils\Tokenizer;

/**
 * Charges a flat cost per call regardless of text length. Real BPE never does
 * that, so any threshold decision this drives is one the bundled tokenizer
 * could not have made for the same input.
 */
final class FlatRateTokenizer implements CanCountTokens
{
    public int $calls = 0;

    public function __construct(private readonly int $cost) {}

    #[Override]
    public function tokenCount(string $text): int {
        $this->calls++;
        return $this->cost;
    }
}

afterEach(fn() => Tokenizer::reset());

function conversation(): Messages {
    return Messages::fromString('one', 'user')
        ->appendMessage(Message::fromString('two', 'assistant'));
}

test('MoveMessagesToBufferHook honours the injected counter', function () {
    $tokenizer = new FlatRateTokenizer(cost: 0);
    $hook = new MoveMessagesToBufferHook(
        maxTokens: 0,
        bufferSection: 'buffer',
        tokenizer: $tokenizer,
    );

    // A zero-token limit would move everything under any real tokenizer. Nothing
    // moving means the free counter was the one consulted.
    $result = $hook->handle(new HookContext(HookTrigger::AfterStep, (new AgentState)->withMessages(conversation())));

    expect($result->state()->store()->section('buffer')->messages()->isEmpty())->toBeTrue()
        ->and(trim($result->state()->messages()->toString()))->toBe("one\ntwo")
        ->and($tokenizer->calls)->toBe(1);
});

test('MoveMessagesToBufferHook buffers when the injected counter exceeds the limit', function () {
    $hook = new MoveMessagesToBufferHook(
        maxTokens: 1,
        bufferSection: 'buffer',
        tokenizer: new FlatRateTokenizer(cost: 2),
    );

    $result = $hook->handle(new HookContext(HookTrigger::AfterStep, (new AgentState)->withMessages(conversation())));

    // Every message costs 2 under this counter, so none of them fits the limit
    // of 1 and the whole conversation overflows.
    expect(trim($result->state()->store()->section('buffer')->messages()->toString()))->toBe("one\ntwo")
        ->and($result->state()->messages()->isEmpty())->toBeTrue();
});

test('MoveMessagesToBufferHook resolves the default tokenizer on use, not on construction', function () {
    // Registered before any override exists, so an eager constructor would hold
    // the BPE default - under which a two-message conversation exceeds a limit
    // of zero and gets buffered.
    $hook = new MoveMessagesToBufferHook(maxTokens: 0, bufferSection: 'buffer');

    $tokenizer = new FlatRateTokenizer(cost: 0);
    Tokenizer::setDefault($tokenizer);
    $result = $hook->handle(new HookContext(HookTrigger::AfterStep, (new AgentState)->withMessages(conversation())));

    // Nothing moved, and the counter installed after construction was consulted.
    expect($result->state()->store()->section('buffer')->messages()->isEmpty())->toBeTrue()
        ->and($tokenizer->calls)->toBe(1);
});

test('UseSummarization passes its tokenizer down to the hooks', function () {
    $summarizer = new class implements CanSummarizeMessages {
        public function summarize(Messages $messages, int $tokenLimit): string {
            return 'summary';
        }
    };
    $tokenizer = new FlatRateTokenizer(cost: 1_000_000);

    $agent = AgentBuilder::base()
        ->withCapability(new UseDriver(new FakeAgentDriver([ScenarioStep::final('ok')])))
        ->withCapability(new UseSummarization(
            policy: new SummarizationPolicy(maxMessageTokens: 4096, maxBufferTokens: 8192, maxSummaryTokens: 64),
            summarizer: $summarizer,
            tokenizer: $tokenizer,
        ))
        ->build();

    $next = null;
    foreach ($agent->iterate((new AgentState)->withMessages(conversation())) as $stepState) {
        $next = $stepState;
        break;
    }

    // A three-word conversation is nowhere near the 4096/8192-token limits under
    // real BPE, so both hooks firing - messages into the buffer, buffer into the
    // summary - can only come from the injected counter.
    expect(trim($next->store()->section('summary')->messages()->toString()))->toBe('summary')
        ->and($next->store()->section('buffer')->messages()->isEmpty())->toBeTrue()
        ->and($tokenizer->calls)->toBeGreaterThan(0);
});
