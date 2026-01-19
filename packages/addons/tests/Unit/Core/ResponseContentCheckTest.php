<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Core;

use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;
use Cognesy\Addons\StepByStep\Continuation\Criteria\ResponseContentCheck;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;

class TestState {
    public function __construct(
        public ?Messages $messages = null
    ) {}
}

it('calls predicate with correct Messages type', function () {
    $testMessages = new Messages(
        new Message('user', 'Hello'),
        new Message('assistant', 'Hi there!')
    );

    $responseResolver = fn(TestState $state): ?Messages => $state->messages;
    $predicateCalls = [];

    $predicate = function(Messages $messages) use (&$predicateCalls): bool {
        $predicateCalls[] = [
            'type' => get_class($messages),
            'count' => $messages->count(),
            'lastContent' => $messages->last()->content()->toString()
        ];
        return true;
    };

    $check = new ResponseContentCheck($responseResolver, $predicate);
    $state = new TestState($testMessages);

    $result = $check->evaluate($state);

    // Predicate returns true → RequestContinuation (work driver has work to do)
    expect($result->decision)->toBe(ContinuationDecision::RequestContinuation);
    expect($predicateCalls)->toHaveCount(1);
    expect($predicateCalls[0]['type'])->toBe('Cognesy\Messages\Messages');
    expect($predicateCalls[0]['count'])->toBe(2);
    expect($predicateCalls[0]['lastContent'])->toBe('Hi there!');
});

it('returns AllowContinuation when response resolver returns null (permits bootstrap)', function () {
    $responseResolver = fn(TestState $state): ?Messages => null;
    $predicate = fn(Messages $messages): bool => false; // Should not be called

    $check = new ResponseContentCheck($responseResolver, $predicate);
    $state = new TestState();

    $result = $check->evaluate($state);

    // Null response means "no step yet, permit bootstrap" (act like a guard)
    expect($result->decision)->toBe(ContinuationDecision::AllowContinuation);
});

it('correctly evaluates non-empty content predicate', function () {
    $nonEmptyMessages = new Messages(new Message('assistant', 'Hello'));
    $emptyMessages = new Messages(new Message('assistant', ''));

    $responseResolver = fn(TestState $state): ?Messages => $state->messages;
    $predicate = fn(Messages $messages): bool => $messages->last()->content()->toString() !== '';

    $check = new ResponseContentCheck($responseResolver, $predicate);

    // Test with non-empty content: predicate true → RequestContinuation
    $result1 = $check->evaluate(new TestState($nonEmptyMessages));
    expect($result1->decision)->toBe(ContinuationDecision::RequestContinuation);

    // Test with empty content: predicate false → AllowStop
    $result2 = $check->evaluate(new TestState($emptyMessages));
    expect($result2->decision)->toBe(ContinuationDecision::AllowStop);
});

it('works with multiple messages and evaluates last message correctly', function () {
    $multipleMessages = new Messages(
        new Message('user', 'Question?'),
        new Message('assistant', 'Answer'),
        new Message('user', 'Follow-up?'),
        new Message('assistant', '') // Last message is empty
    );

    $responseResolver = fn(TestState $state): ?Messages => $state->messages;
    $predicate = fn(Messages $messages): bool => $messages->last()->content()->toString() !== '';

    $check = new ResponseContentCheck($responseResolver, $predicate);
    $state = new TestState($multipleMessages);

    $result = $check->evaluate($state);

    // Should return AllowStop because predicate returns false (last message is empty)
    expect($result->decision)->toBe(ContinuationDecision::AllowStop);
});

it('can be used with different predicate logic', function () {
    $messages = new Messages(
        new Message('assistant', 'This is a response with enough content')
    );

    $responseResolver = fn(TestState $state): ?Messages => $state->messages;

    // Predicate that checks content length
    $lengthPredicate = fn(Messages $messages): bool =>
        strlen($messages->last()->content()->toString()) > 10;

    $check = new ResponseContentCheck($responseResolver, $lengthPredicate);
    $state = new TestState($messages);

    $result = $check->evaluate($state);
    // Long content → predicate true → RequestContinuation
    expect($result->decision)->toBe(ContinuationDecision::RequestContinuation);

    // Test with short content
    $shortMessages = new Messages(new Message('assistant', 'Short'));
    $shortResult = $check->evaluate(new TestState($shortMessages));
    // Short content → predicate false → AllowStop
    expect($shortResult->decision)->toBe(ContinuationDecision::AllowStop);
});

it('handles Messages collection properly when checking for continuation patterns', function () {
    // This test specifically validates the pattern used in ChatWithManyParticipants
    $responseResolver = fn(TestState $state): ?Messages => $state->messages;

    // The exact predicate pattern from the fixed example
    $predicate = static fn(Messages $lastResponse): bool =>
        $lastResponse->last()->content()->toString() !== '';

    $check = new ResponseContentCheck($responseResolver, $predicate);

    // Test continuation with valid content: predicate true → RequestContinuation
    $validMessages = new Messages(new Message('assistant', 'Valid response'));
    $result1 = $check->evaluate(new TestState($validMessages));
    expect($result1->decision)->toBe(ContinuationDecision::RequestContinuation);

    // Test stopping with empty content: predicate false → AllowStop
    $emptyMessages = new Messages(new Message('assistant', ''));
    $result2 = $check->evaluate(new TestState($emptyMessages));
    expect($result2->decision)->toBe(ContinuationDecision::AllowStop);

    // Test with whitespace-only content: predicate true (whitespace != empty string) → RequestContinuation
    $whitespaceMessages = new Messages(new Message('assistant', '   '));
    $result3 = $check->evaluate(new TestState($whitespaceMessages));
    expect($result3->decision)->toBe(ContinuationDecision::RequestContinuation); // trim() is not used in the predicate
});
