<?php declare(strict_types=1);

use Cognesy\Addons\Chat\Contracts\CanSummarizeMessages;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Utils\SplitMessages;
use Cognesy\Addons\StepByStep\StateProcessing\Processors\MoveMessagesToBuffer;
use Cognesy\Addons\StepByStep\StateProcessing\Processors\SummarizeBuffer;
use Cognesy\Addons\Tests\Support\FixedCostTokenizer;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Messages\MessageStore\Section;
use Cognesy\Utils\Tokenizer;

// The injected counter charges one token per message no matter how long it is.
// Under real BPE the long third message alone costs more than the limits used
// below, so every assertion here fails if the component quietly falls back to
// the bundled tokenizer.

afterEach(fn() => Tokenizer::reset());

const SHORT_MESSAGE = 'First message.';
const LONG_MESSAGE = 'Third message, which runs considerably longer than the other two do.';

function threeMessages(): Messages {
    return Messages::fromString(SHORT_MESSAGE, 'user')
        ->appendMessage(Message::fromString('Second message.', 'assistant'))
        ->appendMessage(Message::fromString(LONG_MESSAGE, 'user'));
}

test('SplitMessages splits on the injected token counter', function () {
    $tokenizer = new FixedCostTokenizer(costPerCall: 1);

    [$keep, $overflow] = (new SplitMessages($tokenizer))->split(threeMessages(), tokenLimit: 2);

    // Flat cost of 1 keeps exactly the last two messages...
    expect($keep->count())->toBe(2)
        ->and($overflow->count())->toBe(1)
        ->and(trim($overflow->toString()))->toBe(SHORT_MESSAGE)
        // ...and the counter saw every message, not just some of them.
        ->and($tokenizer->seen)->toHaveCount(3);
});

test('real token costs produce a different split than the flat-cost counter', function () {
    // Guards the test above: if BPE happened to agree with the fake, the
    // assertions there would prove nothing about which tokenizer was used.
    [$keep, $overflow] = (new SplitMessages())->split(threeMessages(), tokenLimit: 2);

    expect(Tokenizer::tokenCount(LONG_MESSAGE))->toBeGreaterThan(2)
        ->and($keep->count())->toBe(0)
        ->and($overflow->count())->toBe(3);
});

test('SplitMessages falls back to the default tokenizer', function () {
    $tokenLimit = Tokenizer::tokenCount('Second message.');

    [$keep, $overflow] = (new SplitMessages())->split(threeMessages(), tokenLimit: $tokenLimit);

    expect($keep->count() + $overflow->count())->toBe(3)
        ->and(trim($overflow->toString()))->toContain(SHORT_MESSAGE);
});

test('SplitMessages resolves the default tokenizer on use, not on construction', function () {
    // Built before any override exists, so an eager constructor would have
    // captured the BPE default - under which nothing fits a limit of 2.
    $split = new SplitMessages();

    Tokenizer::setDefault(new FixedCostTokenizer(costPerCall: 1));
    [$keep, $overflow] = $split->split(threeMessages(), tokenLimit: 2);

    // Flat cost of 1 keeps two messages, so the counter installed after
    // construction is the one that answered.
    expect($keep->count())->toBe(2)
        ->and($overflow->count())->toBe(1);
});

test('MoveMessagesToBuffer resolves the default tokenizer on use, not on construction', function () {
    $processor = new MoveMessagesToBuffer(maxTokens: 2, bufferSection: 'buffer');

    Tokenizer::setDefault(new FixedCostTokenizer(costPerCall: 1));
    $state = (new ChatState)->withMessages(threeMessages());

    // Under the BPE default an eagerly-resolved processor would find the
    // conversation over the limit; the flat counter puts it at 1 token.
    expect($processor->canProcess($state))->toBeFalse();
});

test('MoveMessagesToBuffer moves messages according to the injected counter', function () {
    $tokenizer = new FixedCostTokenizer(costPerCall: 1);
    $state = (new ChatState)->withMessages(threeMessages());

    $processor = new MoveMessagesToBuffer(
        maxTokens: 2,
        bufferSection: 'buffer',
        tokenizer: $tokenizer,
    );

    // The whole conversation costs 1 token under the fake, so nothing overflows.
    expect($processor->canProcess($state))->toBeFalse();

    $updated = $processor->process($state);

    expect(trim($updated->store()->section('buffer')->messages()->toString()))->toBe(SHORT_MESSAGE)
        ->and($updated->messages()->count())->toBe(2);
});

test('SummarizeBuffer decides on the injected counter', function () {
    $summarizer = new class implements CanSummarizeMessages {
        public function summarize(Messages $messages, int $tokenLimit): string {
            return 'SUMMARY';
        }
    };
    $store = MessageStore::fromSections(
        new Section('buffer', Messages::fromString(LONG_MESSAGE, 'user')),
        new Section('summary', Messages::empty()),
        new Section('messages', Messages::empty()),
    );

    $processor = fn(int $maxBufferTokens, ?FixedCostTokenizer $tokenizer) => new SummarizeBuffer(
        maxBufferTokens: $maxBufferTokens,
        maxSummaryTokens: 100,
        bufferSection: 'buffer',
        summarySection: 'summary',
        summarizer: $summarizer,
        tokenizer: $tokenizer,
    );

    $summaryOf = fn(SummarizeBuffer $p) => trim(
        $p->process(new ChatState(store: $store))->store()->section('summary')->messages()->toString(),
    );

    // Buffer costs 1 token under the fake and well over 5 under real BPE, so the
    // same limit has to yield opposite decisions.
    expect($summaryOf($processor(5, new FixedCostTokenizer(costPerCall: 1))))->toBe('')
        ->and($summaryOf($processor(5, null)))->toBe('SUMMARY');
});
