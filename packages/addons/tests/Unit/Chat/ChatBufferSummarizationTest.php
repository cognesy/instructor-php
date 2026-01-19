<?php declare(strict_types=1);

use Cognesy\Addons\Chat\Contracts\CanSummarizeMessages;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\StepByStep\StateProcessing\Processors\MoveMessagesToBuffer;
use Cognesy\Addons\StepByStep\StateProcessing\Processors\SummarizeBuffer;
use Cognesy\Addons\StepByStep\StateProcessing\StateProcessors;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;

final class TestSummarizer implements CanSummarizeMessages
{
    public ?Messages $lastMessages = null;

    public function __construct(private string $summary) {}

    public function summarize(Messages $messages, int $tokenLimit) : string
    {
        $this->lastMessages = $messages;
        return $this->summary;
    }
}

it('summarizes buffered messages when the token limit is exceeded', function () {
    $summarizer = new TestSummarizer('summary text');

    $processors = new StateProcessors(
        new SummarizeBuffer(
            maxBufferTokens: 1,
            maxSummaryTokens: 50,
            bufferSection: 'buffer',
            summarySection: 'summary',
            summarizer: $summarizer,
        ),
        new MoveMessagesToBuffer(maxTokens: 1, bufferSection: 'buffer'),
    );

    $messages = Messages::fromString(content: 'System context.', role: 'system')
        ->appendMessage(Message::fromString(content: 'First question.', role: 'user'))
        ->appendMessage(Message::fromString(content: 'First answer with enough words to exceed limits.', role: 'assistant'));

    $state = (new ChatState)->withMessages($messages);

    $updated = $processors->apply($state);

    expect($summarizer->lastMessages)->not->toBeNull();
    expect($summarizer->lastMessages?->toString())->toContain('System context.');
    expect(trim($updated->store()->section('summary')->messages()->toString()))->toBe('summary text');
    expect(trim($updated->store()->section('buffer')->messages()->toString()))->toBe('');
});
