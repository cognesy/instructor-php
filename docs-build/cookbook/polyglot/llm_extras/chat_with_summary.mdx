<?php declare(strict_types=1);

require 'examples/boot.php';

use Cognesy\Addons\Chat\ChatFactory;
use Cognesy\Addons\Chat\Collections\Participants;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Events\MessageBufferSummarized;
use Cognesy\Addons\Chat\Events\MessagesMovedToBuffer;
use Cognesy\Addons\Chat\Participants\LLMParticipant;
use Cognesy\Addons\Chat\Participants\ScriptedParticipant;
use Cognesy\Addons\Chat\Utils\SummarizeMessages;
use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\Continuation\Criteria\ResponseContentCheck;
use Cognesy\Addons\StepByStep\Continuation\Criteria\StepsLimit;
use Cognesy\Addons\StepByStep\StateProcessing\Processors\AccumulateTokenUsage;
use Cognesy\Addons\StepByStep\StateProcessing\Processors\AppendStepMessages;
use Cognesy\Addons\StepByStep\StateProcessing\Processors\MoveMessagesToBuffer;
use Cognesy\Addons\StepByStep\StateProcessing\Processors\SummarizeBuffer;
use Cognesy\Addons\StepByStep\StateProcessing\StateProcessors;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Utils\Tokenizer;

$events = new EventDispatcher();
$bufferTokenLimit = 600;
$summaryTriggerLimit = 200;
$summaryMaxTokens = 512;
$bufferTokens = 0;

$events->addListener(MessagesMovedToBuffer::class, function(MessagesMovedToBuffer $event) use (
    $bufferTokenLimit,
    $summaryTriggerLimit,
    &$bufferTokens
): void {
    $overflow = Messages::fromArray($event->data['overflow'] ?? []);
    $keep = Messages::fromArray($event->data['keep'] ?? []);
    $overflowTokens = Tokenizer::tokenCount($overflow->toString());
    if ($overflowTokens === 0) {
        return;
    }
    $keepTokens = Tokenizer::tokenCount($keep->toString());
    $totalTokens = $overflowTokens + $keepTokens;
    $bufferTokens += $overflowTokens;

    echo "\n>>> Buffer overflow detected (messages {$totalTokens} tokens > {$bufferTokenLimit}). ";
    echo "Moved {$overflowTokens} tokens to buffer ({$bufferTokens}/{$summaryTriggerLimit}).\n";
});

$events->addListener(MessageBufferSummarized::class, function(MessageBufferSummarized $event) use (
    $summaryTriggerLimit,
    &$bufferTokens
): void {
    $buffer = Messages::fromArray($event->data['buffer'] ?? []);
    $bufferTokens = Tokenizer::tokenCount($buffer->toString());
    $summaryText = (string) ($event->data['summary'] ?? '');
    $summaryTokens = Tokenizer::tokenCount($summaryText);

    echo "\n>>> Summarization triggered ({$bufferTokens} tokens > {$summaryTriggerLimit}).\n";
    echo ">>> Summary tokens: {$summaryTokens}\n";
    $bufferTokens = 0;
});

$student = new ScriptedParticipant(
    name: 'student',
    messages: [
        'Help me get better sales results.',
        'What should I do next?',
        'Give me one more actionable tip.',
        'How could I apply this in practice?',
        "What are some common pitfalls to avoid?",
        "Is there a specific mindset I should adopt?",
        "Can you provide an example of a successful sales interaction using Challenger Sale?",
        "How can I tailor my approach to different types of clients?",
        "What questions should I be asking my prospects?",
        "How do I handle objections effectively?",
        "What should I focus on to improve my sales approach?",
        "How can I measure the success of these strategies?",
        "What resources can I use to learn more about Challenger Sale?",
        "Any final advice for implementing these techniques effectively?",
        '' // Empty string to signal end of conversation
    ],
);

$expert = new LLMParticipant(
    name: 'expert',
    llmProvider: LLMProvider::using('openai'),
    systemPrompt: 'You are a helpful assistant explaining Challenger Sale. Be very brief (one sentence), pragmatic and focused on practical bizdev problems.'
);

// Build a Chat with summary + buffer processors and an assistant participant
$chat = ChatFactory::default(
    participants: new Participants($student, $expert),
    continuationCriteria: new ContinuationCriteria(
        new StepsLimit(30, fn(ChatState $state): int => $state->stepCount()),
        new ResponseContentCheck(
            fn(ChatState $state): ?Messages => $state->currentStep()?->outputMessages(),
            static fn(Messages $lastResponse): bool => $lastResponse->toString() !== '',
        ),
    ),
    processors: new StateProcessors(
        new SummarizeBuffer(
            maxBufferTokens: $summaryTriggerLimit,
            maxSummaryTokens: $summaryMaxTokens,
            bufferSection: 'buffer',
            summarySection: 'summary',
            summarizer: new SummarizeMessages(llm: LLMProvider::using('openai')),
            events: $events,
        ),
        new MoveMessagesToBuffer(
            maxTokens: $bufferTokenLimit,
            bufferSection: 'buffer',
            events: $events
        ),
        new AppendStepMessages(),
        new AccumulateTokenUsage(),
    ),
    events: $events,
);//->wiretap(fn(Event $e) => $e->printDebug());

$context = "# CONTEXT\n\nThis is a long-running coaching session about improving sales performance using the Challenger Sale approach.";
$seedHistory = Messages::fromString(content: $context, role: 'system')
    ->appendMessage(Message::fromString(
        content: 'I keep stalling after the first discovery call and need a clear Challenger-style way to reframe the conversation and surface urgency.',
        role: 'user'
    ))
    ->appendMessage(Message::fromString(
        content: 'Lead with a tailored insight that challenges their current assumption, then ask two pointed questions that quantify the gap and cost.',
        role: 'assistant'
    ))
    ->appendMessage(Message::fromString(
        content: 'Prospects compare us to cheaper tools, so I need a concise way to defend ROI without sounding defensive.',
        role: 'user'
    ))
    ->appendMessage(Message::fromString(
        content: 'Use a short payback story with a concrete metric, then contrast the cost of delay against a specific business outcome.',
        role: 'assistant'
    ))
    ->appendMessage(Message::fromString(
        content: 'We sell across industries and I struggle to adapt the story without rebuilding the whole deck.',
        role: 'user'
    ))
    ->appendMessage(Message::fromString(
        content: 'Keep a core narrative and swap only the pains and metrics so the challenge-then-resolution arc stays consistent.',
        role: 'assistant'
    ));

$state = (new ChatState)->withMessages($seedHistory);

echo "Messages keep at most {$bufferTokenLimit} tokens; buffer summarizes past {$summaryTriggerLimit} tokens.\n";

while ($chat->hasNextStep($state)) {
    $state = $chat->nextStep($state);
    $step = $state->currentStep();

    $name = $step?->participantName() ?? 'unknown';
    $content = trim($step?->outputMessages()->toString() ?? '');
    echo "\n--- Step " . ($state->stepCount()) . " ($name) ---\n";
    $display = $content;
    if ($display === '') {
        $display = '[eot]';
    }
    echo $display . "\n";
//    echo "---------------------\n";
//    echo "SUMMARY:\n" . $state->store()->section('summary')->get()?->toString();
//    echo "---------------------\n";
//    echo "BUFFER:\n" . $state->store()->section('buffer')->get()?->toString();
//    echo "---------------------\n";
//    echo "MESSAGES:\n" . $state->store()->section('messages')->get()?->toString();
//    echo "=====================\n";
}

$summaryText = trim($state->store()->section('summary')->messages()->toString());
if ($summaryText !== '') {
    echo "\n--- Final Summary ---\n";
    echo $summaryText . "\n";
}
?>
