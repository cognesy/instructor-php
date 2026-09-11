<?php

use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Messages\MessageStore\Section;

it('creates messages from script', function () {
    $store = MessageStore::fromSections(
        new Section('section-1'),
        new Section('section-2'),
    );
    $store = $store->parameters()->withParams([
        'key-1' => 'value-1',
        'key-2' => 'value-2',
    ]);

    $store = $store->section('section-1')->appendMessages(['role' => 'user', 'content' => 'content-1']);
    $store = $store->section('section-1')->appendMessages(['role' => 'assistant', 'content' => 'content-2']);
    $store = $store->section('section-1')->appendMessages(['role' => 'user', 'content' => 'content-3 <|key-1|>']);

    $store = $store->section('section-2')->appendMessages(['role' => 'user', 'content' => 'content-4']);
    $store = $store->section('section-2')->appendMessages(['role' => 'assistant', 'content' => 'content-5']);
    $store = $store->section('section-2')->appendMessages(['role' => 'user', 'content' => 'content-6 <|key-2|>']);

    $messages = $store->toMessages();
    $messageList = $messages->all();

    expect($messages)->toHaveCount(6);
    expect($messageList[0]->role()->value)->toBe('user');
    expect($messageList[0]->content()->toString())->toBe('content-1');
    expect($messageList[1]->role()->value)->toBe('assistant');
    expect($messageList[1]->content()->toString())->toBe('content-2');
    expect($messageList[2]->role()->value)->toBe('user');
    expect($messageList[2]->content()->toString())->toBe('content-3 <|key-1|>');
    expect($messageList[3]->role()->value)->toBe('user');
    expect($messageList[3]->content()->toString())->toBe('content-4');
    expect($messageList[4]->role()->value)->toBe('assistant');
    expect($messageList[4]->content()->toString())->toBe('content-5');
    expect($messageList[5]->role()->value)->toBe('user');
    expect($messageList[5]->content()->toString())->toBe('content-6 <|key-2|>');
});


it('selects sections from script', function () {
    $store = MessageStore::fromSections(
        new Section('section-1'),
        new Section('section-2'),
        new Section('section-3'),
    );
    $store = $store->parameters()->withParams([
        'key-1' => 'value-1',
        'key-2' => 'value-2',
    ]);

    $store = $store->section('section-1')->appendMessages(['role' => 'user', 'content' => 'content-1']);
    $store = $store->section('section-1')->appendMessages(['role' => 'assistant', 'content' => 'content-2']);
    $store = $store->section('section-1')->appendMessages(['role' => 'user', 'content' => 'content-3 <|key-1|>']);

    $store = $store->section('section-2')->appendMessages(['role' => 'user', 'content' => 'content-4']);
    $store = $store->section('section-2')->appendMessages(['role' => 'assistant', 'content' => 'content-5']);
    $store = $store->section('section-2')->appendMessages(['role' => 'user', 'content' => 'content-6']);

    $store = $store->section('section-3')->appendMessages(['role' => 'user', 'content' => 'content-7']);
    $store = $store->section('section-3')->appendMessages(['role' => 'assistant', 'content' => 'content-8']);
    $store = $store->section('section-3')->appendMessages(['role' => 'user', 'content' => 'content-9 <|key-2|>']);

    $messages = $store->select(['section-3', 'section-1'])->toMessages();
    $messageList = $messages->all();

    expect($messages)->toHaveCount(6);
    expect($messageList[0]->role()->value)->toBe('user');
    expect($messageList[0]->content()->toString())->toBe('content-7');
    expect($messageList[1]->role()->value)->toBe('assistant');
    expect($messageList[1]->content()->toString())->toBe('content-8');
    expect($messageList[2]->role()->value)->toBe('user');
    expect($messageList[2]->content()->toString())->toBe('content-9 <|key-2|>');
    expect($messageList[3]->role()->value)->toBe('user');
    expect($messageList[3]->content()->toString())->toBe('content-1');
    expect($messageList[4]->role()->value)->toBe('assistant');
    expect($messageList[4]->content()->toString())->toBe('content-2');
    expect($messageList[5]->role()->value)->toBe('user');
    expect($messageList[5]->content()->toString())->toBe('content-3 <|key-1|>');
});



it('translates messages to string', function () {
    $store = MessageStore::fromSections(
        new Section('section-1'),
        new Section('section-2'),
    );
    $store = $store->parameters()->withParams([
        'key-1' => 'value-1',
        'key-2' => 'value-2',
    ]);
    $store = $store->section('section-1')->appendMessages(['role' => 'user', 'content' => 'content-1']);
    $store = $store->section('section-1')->appendMessages(['role' => 'assistant', 'content' => 'content-2']);
    $store = $store->section('section-1')->appendMessages(['role' => 'user', 'content' => 'content-3 <|key-1|>']);

    $store = $store->section('section-2')->appendMessages(['role' => 'user', 'content' => 'content-4']);
    $store = $store->section('section-2')->appendMessages(['role' => 'assistant', 'content' => 'content-5']);
    $store = $store->section('section-2')->appendMessages(['role' => 'user', 'content' => 'content-6 <|key-2|>']);

    $text = $store->select(['section-2', 'section-1'])->toString();
    expect($text)->toBe("content-4\ncontent-5\ncontent-6 <|key-2|>\ncontent-1\ncontent-2\ncontent-3 <|key-1|>\n");

    $text = $store->select('section-1')->toString();
    expect($text)->toBe("content-1\ncontent-2\ncontent-3 <|key-1|>\n");
});
