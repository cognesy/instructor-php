<?php declare(strict_types=1);

use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\AgentBuilder\Capabilities\Summarization\Contracts\CanSummarizeMessages;
use Cognesy\Agents\AgentBuilder\Capabilities\Summarization\SummarizationPolicy;
use Cognesy\Agents\AgentBuilder\Capabilities\Summarization\UseSummarization;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Utils\Tokenizer;

it('moves overflow messages into the buffer when summarization is enabled', function () {
    $summarizer = new class implements CanSummarizeMessages {
        public function summarize(Messages $messages, int $tokenLimit): string {
            return 'summary';
        }
    };

    $tokenLimit = Tokenizer::tokenCount('ok');
    $policy = new SummarizationPolicy(
        maxMessageTokens: $tokenLimit,
        maxBufferTokens: 1000,
        maxSummaryTokens: 64,
    );

    $agent = AgentBuilder::base()
        ->withDriver(new FakeAgentDriver([ScenarioStep::final('ok')]))
        ->withCapability(new UseSummarization($policy, $summarizer))
        ->build();

    $messages = Messages::fromString('one', 'user')
        ->appendMessage(Message::fromString('two', 'assistant'));
    $state = (new AgentState)->withMessages($messages);

    // Get first step from iterate()
    $next = null;
    foreach ($agent->iterate($state) as $stepState) {
        $next = $stepState;
        break;
    }

    expect(trim($next->messages()->toString()))->toBe('ok');
    expect(trim($next->store()->section('buffer')->messages()->toString()))
        ->toBe("one\ntwo");
});
