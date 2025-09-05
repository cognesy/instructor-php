<?php declare(strict_types=1);

use Cognesy\Addons\Chat\Processors\Messages\NormalizeRolesForActiveParticipant;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;

it('normalizes roles so active participant is assistant and others are user', function () {
    $state = new ChatState();
    $state = $state->withVariable('active_participant_id', 'assistantA');

    $m1 = (new Message('assistant', 'Hi from A'))->withMeta('participantId', 'assistantA');
    $m2 = (new Message('assistant', 'Hi from B'))->withMeta('participantId', 'assistantB');
    $sys = new Message('system', 'rules');
    $messages = new Messages($sys, $m1, $m2);

    $proc = new NormalizeRolesForActiveParticipant();
    $out = $proc->beforeSend($messages, $state);
    $arr = $out->toArray();

    expect($arr[0]['role'])->toBe('system');
    // After normalization: A stays assistant, B becomes user
    expect($arr[1]['role'])->toBe('assistant');
    expect($arr[1]['_metadata']['participantId'])->toBe('assistantA');
    expect($arr[2]['role'])->toBe('user');
    expect($arr[2]['_metadata']['participantId'])->toBe('assistantB');
});
