<?php declare(strict_types=1);

use Cognesy\Addons\Core\Continuation\Criteria\ResponseContentCheck;
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

    $result = $check->canContinue($state);

    expect($result)->toBeTrue();
    expect($predicateCalls)->toHaveCount(1);
    expect($predicateCalls[0]['type'])->toBe('Cognesy\Messages\Messages');
    expect($predicateCalls[0]['count'])->toBe(2);
    expect($predicateCalls[0]['lastContent'])->toBe('Hi there!');
});

it('returns true when response resolver returns null', function () {
    $responseResolver = fn(TestState $state): ?Messages => null;
    $predicate = fn(Messages $messages): bool => false; // Should not be called

    $check = new ResponseContentCheck($responseResolver, $predicate);
    $state = new TestState();

    $result = $check->canContinue($state);

    expect($result)->toBeTrue();
});

it('correctly evaluates non-empty content predicate', function () {
    $nonEmptyMessages = new Messages(new Message('assistant', 'Hello'));
    $emptyMessages = new Messages(new Message('assistant', ''));

    $responseResolver = fn(TestState $state): ?Messages => $state->messages;
    $predicate = fn(Messages $messages): bool => $messages->last()->content()->toString() !== '';

    $check = new ResponseContentCheck($responseResolver, $predicate);

    // Test with non-empty content
    $result1 = $check->canContinue(new TestState($nonEmptyMessages));
    expect($result1)->toBeTrue();

    // Test with empty content
    $result2 = $check->canContinue(new TestState($emptyMessages));
    expect($result2)->toBeFalse();
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

    $result = $check->canContinue($state);

    // Should return false because last message is empty
    expect($result)->toBeFalse();
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

    $result = $check->canContinue($state);
    expect($result)->toBeTrue();

    // Test with short content
    $shortMessages = new Messages(new Message('assistant', 'Short'));
    $shortResult = $check->canContinue(new TestState($shortMessages));
    expect($shortResult)->toBeFalse();
});

it('handles Messages collection properly when checking for continuation patterns', function () {
    // This test specifically validates the pattern used in ChatWithManyParticipants
    $responseResolver = fn(TestState $state): ?Messages => $state->messages;

    // The exact predicate pattern from the fixed example
    $predicate = static fn(Messages $lastResponse): bool =>
        $lastResponse->last()->content()->toString() !== '';

    $check = new ResponseContentCheck($responseResolver, $predicate);

    // Test continuation with valid content
    $validMessages = new Messages(new Message('assistant', 'Valid response'));
    $result1 = $check->canContinue(new TestState($validMessages));
    expect($result1)->toBeTrue();

    // Test stopping with empty content
    $emptyMessages = new Messages(new Message('assistant', ''));
    $result2 = $check->canContinue(new TestState($emptyMessages));
    expect($result2)->toBeFalse();

    // Test stopping with whitespace-only content
    $whitespaceMessages = new Messages(new Message('assistant', '   '));
    $result3 = $check->canContinue(new TestState($whitespaceMessages));
    expect($result3)->toBeTrue(); // trim() is not used in the predicate
});