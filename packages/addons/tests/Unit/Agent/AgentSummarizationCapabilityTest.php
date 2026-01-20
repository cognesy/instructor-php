<?php declare(strict_types=1);

use Cognesy\Addons\AgentBuilder\AgentBuilder;
use Cognesy\Addons\AgentBuilder\Capabilities\Summarization\SummarizationPolicy;
use Cognesy\Addons\AgentBuilder\Capabilities\Summarization\UseSummarization;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Drivers\Testing\DeterministicAgentDriver;
use Cognesy\Addons\Agent\Drivers\Testing\ScenarioStep;
use Cognesy\Addons\Chat\Contracts\CanSummarizeMessages;
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

    $agent = AgentBuilder::new()
        ->withDriver(new DeterministicAgentDriver([ScenarioStep::final('ok')]))
        ->withCapability(new UseSummarization($policy, $summarizer))
        ->build();

    $messages = Messages::fromString('one', 'user')
        ->appendMessage(Message::fromString('two', 'assistant'));
    $state = (new AgentState)->withMessages($messages);

    $next = $agent->nextStep($state);

    expect(trim($next->messages()->toString()))->toBe('ok');
    expect(trim($next->store()->section('buffer')->messages()->toString()))
        ->toBe("one\ntwo");
});
