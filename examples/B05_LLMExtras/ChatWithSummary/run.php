<?php

require 'examples/boot.php';

use Cognesy\Addons\Chat\ChatFactory;
use Cognesy\Addons\Chat\Collections\Participants;
use Cognesy\Addons\Chat\Data\ChatState;
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
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\LLMProvider;

$events = new EventDispatcher();

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
        new AccumulateTokenUsage(),
        new AppendStepMessages(),
        new MoveMessagesToBuffer(
            maxTokens: 128,
            bufferSection: 'buffer',
            events: $events
        ),
        new SummarizeBuffer(
            maxBufferTokens: 128,
            maxSummaryTokens: 512,
            bufferSection: 'buffer',
            summarySection: 'summary',
            summarizer: new SummarizeMessages(llm: LLMProvider::using('openai')),
            events: $events,
        ),
    ),
    events: $events,
);//->wiretap(fn(Event $e) => $e->printDebug());

$context = "# CONTEXT\n\n" . file_get_contents(__DIR__ . '/summary.md');

$state = (new ChatState)->withMessages(
    Messages::fromString(content: $context, role: 'system')
);

while ($chat->hasNextStep($state)) {
    $state = $chat->nextStep($state);
    $step = $state->currentStep();

    $name = $step?->participantName() ?? 'unknown';
    $content = trim($step?->outputMessages()->toString() ?? '');
    echo "\n--- Step " . ($state->stepCount()) . " ($name) ---\n";
    echo ($content ?: '[eot]'). "\n";
//    echo "---------------------\n";
//    echo "SUMMARY:\n" . $state->store()->section('summary')->get()?->toString();
//    echo "---------------------\n";
//    echo "BUFFER:\n" . $state->store()->section('buffer')->get()?->toString();
//    echo "---------------------\n";
//    echo "MESSAGES:\n" . $state->store()->section('messages')->get()?->toString();
//    echo "=====================\n";
}
?>
