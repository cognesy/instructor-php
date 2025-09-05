<?php declare(strict_types=1);

use Cognesy\Addons\Chat\Chat;
use Cognesy\Addons\Chat\ContinuationCriteria\StepsLimit;
use Cognesy\Addons\Chat\Participants\HumanParticipant;
use Cognesy\Addons\Chat\Processors\MoveMessagesToBuffer;
use Cognesy\Template\Script\Script;

it('moves messages from main to buffer when token limit exceeded', function () {
    $script = (new Script())->withSection('summary')->withSection('buffer')->withSection('main');
    $state = new \Cognesy\Addons\Chat\Data\ChatState($script);

    $human = new HumanParticipant(id: 'user', messageProvider: fn() => str_repeat('x', 10));
    $chat = new Chat(state: $state, continuationCriteria: [new StepsLimit(1)]);
    $chat->withParticipants([$human]);
    $chat->withScriptProcessors(new MoveMessagesToBuffer('main', 'buffer', 0));

    $chat->finalTurn();

    $main = $chat->state()->script()->section('main')->toMessages()->toArray();
    $buffer = $chat->state()->script()->section('buffer')->toMessages()->toArray();

    expect(count($main))->toBe(0);
    expect(count($buffer))->toBe(1);
    expect($buffer[0]['content'])->toBe(str_repeat('x', 10));
});

