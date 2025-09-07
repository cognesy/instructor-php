<?php

use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Messages\MessageStore\Section;

it('creates messages from script', function () {
    $store = MessageStore::fromSections(
        new Section('section-1'),
        new Section('section-2'),
    );
    $store = $store->withParams([
        'key-1' => 'value-1',
        'key-2' => 'value-2',
    ]);

    $store = $store->appendMessageToSection('section-1', ['role' => 'user', 'content' => 'content-1']);
    $store = $store->appendMessageToSection('section-1', ['role' => 'assistant', 'content' => 'content-2']);
    $store = $store->appendMessageToSection('section-1', ['role' => 'user', 'content' => 'content-3 <|key-1|>']);

    $store = $store->appendMessageToSection('section-2', ['role' => 'user', 'content' => 'content-4']);
    $store = $store->appendMessageToSection('section-2', ['role' => 'assistant', 'content' => 'content-5']);
    $store = $store->appendMessageToSection('section-2', ['role' => 'user', 'content' => 'content-6 <|key-2|>']);

    $messages = $store->toArray();

    expect(count($messages))->toBe(6);
    expect($messages[0]['role'])->toBe('user');
    expect($messages[0]['content'])->toBe('content-1');
    expect($messages[1]['role'])->toBe('assistant');
    expect($messages[1]['content'])->toBe('content-2');
    expect($messages[2]['role'])->toBe('user');
    expect($messages[2]['content'])->toBe('content-3 <|key-1|>');
    expect($messages[3]['role'])->toBe('user');
    expect($messages[3]['content'])->toBe('content-4');
    expect($messages[4]['role'])->toBe('assistant');
    expect($messages[4]['content'])->toBe('content-5');
    expect($messages[5]['role'])->toBe('user');
    expect($messages[5]['content'])->toBe('content-6 <|key-2|>');
});


it('selects sections from script', function () {
    $store = MessageStore::fromSections(
        new Section('section-1'),
        new Section('section-2'),
        new Section('section-3'),
    );
    $store = $store->withParams([
        'key-1' => 'value-1',
        'key-2' => 'value-2',
    ]);

    $store = $store->appendMessageToSection('section-1', ['role' => 'user', 'content' => 'content-1']);
    $store = $store->appendMessageToSection('section-1', ['role' => 'assistant', 'content' => 'content-2']);
    $store = $store->appendMessageToSection('section-1', ['role' => 'user', 'content' => 'content-3 <|key-1|>']);

    $store = $store->appendMessageToSection('section-2', ['role' => 'user', 'content' => 'content-4']);
    $store = $store->appendMessageToSection('section-2', ['role' => 'assistant', 'content' => 'content-5']);
    $store = $store->appendMessageToSection('section-2', ['role' => 'user', 'content' => 'content-6']);

    $store = $store->appendMessageToSection('section-3', ['role' => 'user', 'content' => 'content-7']);
    $store = $store->appendMessageToSection('section-3', ['role' => 'assistant', 'content' => 'content-8']);
    $store = $store->appendMessageToSection('section-3', ['role' => 'user', 'content' => 'content-9 <|key-2|>']);

    $messages = $store->select(['section-3', 'section-1'])->toArray();

    expect(count($messages))->toBe(6);
    expect($messages[0]['role'])->toBe('user');
    expect($messages[0]['content'])->toBe('content-7');
    expect($messages[1]['role'])->toBe('assistant');
    expect($messages[1]['content'])->toBe('content-8');
    expect($messages[2]['role'])->toBe('user');
    expect($messages[2]['content'])->toBe('content-9 <|key-2|>');
    expect($messages[3]['role'])->toBe('user');
    expect($messages[3]['content'])->toBe('content-1');
    expect($messages[4]['role'])->toBe('assistant');
    expect($messages[4]['content'])->toBe('content-2');
    expect($messages[5]['role'])->toBe('user');
    expect($messages[5]['content'])->toBe('content-3 <|key-1|>');
});



it('translates messages to string', function () {
    $store = MessageStore::fromSections(
        new Section('section-1'),
        new Section('section-2'),
    );
    $store = $store->withParams([
        'key-1' => 'value-1',
        'key-2' => 'value-2',
    ]);
    $store = $store->appendMessageToSection('section-1', ['role' => 'user', 'content' => 'content-1']);
    $store = $store->appendMessageToSection('section-1', ['role' => 'assistant', 'content' => 'content-2']);
    $store = $store->appendMessageToSection('section-1', ['role' => 'user', 'content' => 'content-3 <|key-1|>']);

    $store = $store->appendMessageToSection('section-2', ['role' => 'user', 'content' => 'content-4']);
    $store = $store->appendMessageToSection('section-2', ['role' => 'assistant', 'content' => 'content-5']);
    $store = $store->appendMessageToSection('section-2', ['role' => 'user', 'content' => 'content-6 <|key-2|>']);

    $text = $store->select(['section-2', 'section-1'])->toString();
    expect($text)->toBe("content-4\ncontent-5\ncontent-6 <|key-2|>\ncontent-1\ncontent-2\ncontent-3 <|key-1|>\n");

    $text = $store->select('section-1')->toString();
    expect($text)->toBe("content-1\ncontent-2\ncontent-3 <|key-1|>\n");
});

