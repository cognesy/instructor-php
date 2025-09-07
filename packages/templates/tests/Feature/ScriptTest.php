<?php

use Cognesy\Messages\Messages;
use Cognesy\Messages\Script\Script;
use Cognesy\Messages\Script\Section;

it('creates messages from script', function () {
    $script = Script::fromSections(
        new Section('section-1'),
        new Section('section-2'),
    );
    $script = $script->withParams([
        'key-1' => 'value-1',
        'key-2' => 'value-2',
    ]);

    $script = $script->appendMessageToSection('section-1', ['role' => 'user', 'content' => 'content-1']);
    $script = $script->appendMessageToSection('section-1', ['role' => 'assistant', 'content' => 'content-2']);
    $script = $script->appendMessageToSection('section-1', ['role' => 'user', 'content' => 'content-3 <|key-1|>']);

    $script = $script->appendMessageToSection('section-2', ['role' => 'user', 'content' => 'content-4']);
    $script = $script->appendMessageToSection('section-2', ['role' => 'assistant', 'content' => 'content-5']);
    $script = $script->appendMessageToSection('section-2', ['role' => 'user', 'content' => 'content-6 <|key-2|>']);

    $messages = $script->toArray();

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
    $script = Script::fromSections(
        new Section('section-1'),
        new Section('section-2'),
        new Section('section-3'),
    );
    $script = $script->withParams([
        'key-1' => 'value-1',
        'key-2' => 'value-2',
    ]);

    $script = $script->appendMessageToSection('section-1', ['role' => 'user', 'content' => 'content-1']);
    $script = $script->appendMessageToSection('section-1', ['role' => 'assistant', 'content' => 'content-2']);
    $script = $script->appendMessageToSection('section-1', ['role' => 'user', 'content' => 'content-3 <|key-1|>']);

    $script = $script->appendMessageToSection('section-2', ['role' => 'user', 'content' => 'content-4']);
    $script = $script->appendMessageToSection('section-2', ['role' => 'assistant', 'content' => 'content-5']);
    $script = $script->appendMessageToSection('section-2', ['role' => 'user', 'content' => 'content-6']);

    $script = $script->appendMessageToSection('section-3', ['role' => 'user', 'content' => 'content-7']);
    $script = $script->appendMessageToSection('section-3', ['role' => 'assistant', 'content' => 'content-8']);
    $script = $script->appendMessageToSection('section-3', ['role' => 'user', 'content' => 'content-9 <|key-2|>']);

    $messages = $script->select(['section-3', 'section-1'])->toArray();

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
    $script = Script::fromSections(
        new Section('section-1'),
        new Section('section-2'),
    );
    $script = $script->withParams([
        'key-1' => 'value-1',
        'key-2' => 'value-2',
    ]);
    $script = $script->appendMessageToSection('section-1', ['role' => 'user', 'content' => 'content-1']);
    $script = $script->appendMessageToSection('section-1', ['role' => 'assistant', 'content' => 'content-2']);
    $script = $script->appendMessageToSection('section-1', ['role' => 'user', 'content' => 'content-3 <|key-1|>']);

    $script = $script->appendMessageToSection('section-2', ['role' => 'user', 'content' => 'content-4']);
    $script = $script->appendMessageToSection('section-2', ['role' => 'assistant', 'content' => 'content-5']);
    $script = $script->appendMessageToSection('section-2', ['role' => 'user', 'content' => 'content-6 <|key-2|>']);

    $text = $script->select(['section-2', 'section-1'])->toString();
    expect($text)->toBe("content-4\ncontent-5\ncontent-6 <|key-2|>\ncontent-1\ncontent-2\ncontent-3 <|key-1|>\n");

    $text = $script->select('section-1')->toString();
    expect($text)->toBe("content-1\ncontent-2\ncontent-3 <|key-1|>\n");
});

