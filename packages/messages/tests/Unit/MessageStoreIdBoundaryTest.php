<?php declare(strict_types=1);

use Cognesy\Messages\Message;
use Cognesy\Messages\MessageId;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Messages\MessageStore\Section;

it('round-trips message ids through store serialization boundary', function () {
    $store = MessageStore::fromSections(new Section('chat'))
        ->section('chat')
        ->appendMessages(new Message(role: 'user', content: 'Hello'));

    $serialized = $store->toArray();
    $restored = MessageStore::fromArray($serialized);
    $message = $restored->section('chat')->messages()->first();
    $serializedMessage = $serialized['sections'][0]['messages'][0];

    expect($serializedMessage['id'])->toBeString()->not->toBeEmpty()
        ->and($message->id())->toBeInstanceOf(MessageId::class)
        ->and($message->id()->toString())->toBe($serializedMessage['id']);
});
