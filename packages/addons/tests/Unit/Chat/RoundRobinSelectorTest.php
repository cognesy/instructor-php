<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Chat;

use Cognesy\Addons\Chat\Collections\Participants;
use Cognesy\Addons\Chat\Contracts\CanParticipateInChat;
use Cognesy\Addons\Chat\Data\ChatStep;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Selectors\RoundRobin\RoundRobinSelector;

it('selects participants in round-robin order', function () {
    $p1 = new class implements CanParticipateInChat {
        public function name(): string { return 'p1'; }
        public function act(ChatState $state): ChatStep { return new ChatStep('p1'); }
    };
    $p2 = new class implements CanParticipateInChat {
        public function name(): string { return 'p2'; }
        public function act(ChatState $state): ChatStep { return new ChatStep('p2'); }
    };
    
    $participants = new Participants($p1, $p2);
    $state = new ChatState();

    $selector = new RoundRobinSelector();
    expect($selector->nextParticipant($state, $participants)?->name())->toBe('p1');
    expect($selector->nextParticipant($state, $participants)?->name())->toBe('p2');
    expect($selector->nextParticipant($state, $participants)?->name())->toBe('p1');
});
