<?php declare(strict_types=1);

use Cognesy\Addons\Chat\Contracts\CanSummarizeMessages;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\StepByStep\StateProcessing\Processors\SummarizeBuffer;
use Cognesy\Addons\StepByStep\StateProcessing\StateProcessors;
use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Messages\MessageStore\Section;

it('summarizes buffer together with existing summary', function () {
    $summarizer = new class('merged summary') implements CanSummarizeMessages {
        public ?Messages $lastMessages = null;

        public function __construct(private string $summary) {}

        public function summarize(Messages $messages, int $tokenLimit) : string {
            $this->lastMessages = $messages;
            return $this->summary;
        }
    };

    $store = MessageStore::fromSections(
        new Section('summary', Messages::fromString('Summary so far.', 'system')),
        new Section('buffer', Messages::fromString('Buffered context.', 'user')),
    );

    $state = new ChatState(store: $store);

    $processors = new StateProcessors(
        new SummarizeBuffer(
            maxBufferTokens: 1,
            maxSummaryTokens: 50,
            bufferSection: 'buffer',
            summarySection: 'summary',
            summarizer: $summarizer,
        ),
    );

    $updated = $processors->apply($state);

    $inputText = trim($summarizer->lastMessages?->toString() ?? '');
    expect($inputText)->toBe("Buffered context.\nSummary so far.");
    expect(trim($updated->store()->section('summary')->messages()->toString()))->toBe('merged summary');
    expect(trim($updated->store()->section('buffer')->messages()->toString()))->toBe('');
});
