<?php declare(strict_types=1);

use Cognesy\Addons\Chat\Contracts\CanParticipateInChat;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\ChatStep;
use Cognesy\Addons\Chat\Selectors\RoundRobinSelector;
use Cognesy\Template\Script\Script;

it('selects participants in round-robin order', function () {
    $p1 = new class implements CanParticipateInChat {
        public function id(): string { return 'p1'; }
        public function act(ChatState $state): ChatStep { return new ChatStep('p1'); }
    };
    $p2 = new class implements CanParticipateInChat {
        public function id(): string { return 'p2'; }
        public function act(ChatState $state): ChatStep { return new ChatStep('p2'); }
    };
    $state = new ChatState(new Script());
    $state = $state->withParticipants($p1, $p2);

    $selector = new RoundRobinSelector();
    expect($selector->choose($state)?->id())->toBe('p1');
    expect($selector->choose($state)?->id())->toBe('p2');
    expect($selector->choose($state)?->id())->toBe('p1');
});
