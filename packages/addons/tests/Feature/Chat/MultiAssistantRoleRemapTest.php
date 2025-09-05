<?php declare(strict_types=1);

use Cognesy\Addons\Chat\Chat;
use Cognesy\Addons\Chat\Contracts\CanParticipateInChat;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\ChatStep;
use Cognesy\Addons\Chat\Events\ChatBeforeSend;
use Cognesy\Addons\Chat\Selectors\RoundRobinSelector;
use Cognesy\Events\EventBusResolver;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Template\Script\Script;

it('remaps roles per active assistant in multi-participant chat', function () {
    // two dummy assistants
    $a = new class implements CanParticipateInChat {
        public function id(): string { return 'assistantA'; }
        public function act(ChatState $state): ChatStep { return new ChatStep('assistantA', Messages::empty()); }
    };
    $b = new class implements CanParticipateInChat {
        public function id(): string { return 'assistantB'; }
        public function act(ChatState $state): ChatStep { return new ChatStep('assistantB', Messages::empty()); }
    };

    $script = (new Script())
        ->withSection('system')
        ->withSection('summary')
        ->withSection('buffer')
        ->withSection('main')
        ->withSectionMessages('system', Messages::fromString('rules', 'system'))
        ->withSectionMessages('main', (new Messages(
            (new Message('assistant', 'Hi from A'))->withMeta('participantId','assistantA'),
            (new Message('assistant', 'Hi from B'))->withMeta('participantId','assistantB'),
        )));

    $state = new ChatState($script);

    $events = EventBusResolver::default();
    $seen = [];
    $events->wiretap(function($event) use (&$seen) {
        if ($event instanceof ChatBeforeSend) { $seen[] = $event->data; }
    });

    $chat = new Chat(state: $state, selector: new RoundRobinSelector(), events: $events);
    $chat->withParticipants([$a, $b]);

    // Turn 1: assistantA active => A is assistant, B is user
    $chat->nextTurn();
    expect(count($seen))->toBe(1);
    $m = array_map(fn($m) => $m['role'] ?? '', $seen[0]['messages']->toArray());
    expect($m)->toContain('system');
    // ensure both roles present and assistant count == 1
    expect(array_count_values($m)['assistant'] ?? 0)->toBe(1);

    // Turn 2: assistantB active => B is assistant, A is user
    $chat->nextTurn();
    expect(count($seen))->toBe(2);
    $m2 = array_map(fn($m) => $m['role'] ?? '', $seen[1]['messages']->toArray());
    expect(array_count_values($m2)['assistant'] ?? 0)->toBe(1);
});

