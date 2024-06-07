<?php
namespace Tests;

use Cognesy\Instructor\ApiClient\Enums\ClientType;
use Cognesy\Instructor\Core\Messages\Script;
use Cognesy\Instructor\Core\Messages\Section;

it('creates messages from script', function () {
    $script = new Script(
        new Section('section-1'),
        new Section('section-2'),
    );
    $script->setContext([
        'key-1' => 'value-1',
        'key-2' => 'value-2',
    ]);

    $script->section('section-1')->add(['role' => 'user', 'content' => 'content-1']);
    $script->section('section-1')->add(['role' => 'assistant', 'content' => 'content-2']);
    $script->section('section-1')->add(['role' => 'user', 'content' => 'content-3 {key-1}']);

    $script->section('section-2')->add(['role' => 'user', 'content' => 'content-4']);
    $script->section('section-2')->add(['role' => 'assistant', 'content' => 'content-5']);
    $script->section('section-2')->add(['role' => 'user', 'content' => 'content-6 {key-2}']);

    $messages = $script->toArray();

    expect(count($messages))->toBe(6);
    expect($messages[0]['role'])->toBe('user');
    expect($messages[0]['content'])->toBe('content-1');
    expect($messages[1]['role'])->toBe('assistant');
    expect($messages[1]['content'])->toBe('content-2');
    expect($messages[2]['role'])->toBe('user');
    expect($messages[2]['content'])->toBe('content-3 value-1');
    expect($messages[3]['role'])->toBe('user');
    expect($messages[3]['content'])->toBe('content-4');
    expect($messages[4]['role'])->toBe('assistant');
    expect($messages[4]['content'])->toBe('content-5');
    expect($messages[5]['role'])->toBe('user');
    expect($messages[5]['content'])->toBe('content-6 value-2');
});


it('selects sections from script', function () {
    $script = new Script(
        new Section('section-1'),
        new Section('section-2'),
    );
    $script->setContext([
        'key-1' => 'value-1',
        'key-2' => 'value-2',
    ]);

    $script->section('section-1')->add(['role' => 'user', 'content' => 'content-1']);
    $script->section('section-1')->add(['role' => 'assistant', 'content' => 'content-2']);
    $script->section('section-1')->add(['role' => 'user', 'content' => 'content-3 {key-1}']);

    $script->section('section-2')->add(['role' => 'user', 'content' => 'content-4']);
    $script->section('section-2')->add(['role' => 'assistant', 'content' => 'content-5']);
    $script->section('section-2')->add(['role' => 'user', 'content' => 'content-6']);

    $script->section('section-3')->add(['role' => 'user', 'content' => 'content-7']);
    $script->section('section-3')->add(['role' => 'assistant', 'content' => 'content-8']);
    $script->section('section-3')->add(['role' => 'user', 'content' => 'content-9 {key-2}']);

    $messages = $script->select(['section-3', 'section-1'])->toArray();

    expect(count($messages))->toBe(6);
    expect($messages[0]['role'])->toBe('user');
    expect($messages[0]['content'])->toBe('content-7');
    expect($messages[1]['role'])->toBe('assistant');
    expect($messages[1]['content'])->toBe('content-8');
    expect($messages[2]['role'])->toBe('user');
    expect($messages[2]['content'])->toBe('content-9 value-2');
    expect($messages[3]['role'])->toBe('user');
    expect($messages[3]['content'])->toBe('content-1');
    expect($messages[4]['role'])->toBe('assistant');
    expect($messages[4]['content'])->toBe('content-2');
    expect($messages[5]['role'])->toBe('user');
    expect($messages[5]['content'])->toBe('content-3 value-1');
});


it('translates messages to native format - Cohere', function () {
    $script = new Script(
        new Section('section-1'),
        new Section('section-2'),
    );
    $script->setContext([
        'key-1' => 'value-1',
        'key-2' => 'value-2',
    ]);
    $script->section('section-1')->add(['role' => 'user', 'content' => 'content-1']);
    $script->section('section-1')->add(['role' => 'assistant', 'content' => 'content-2']);
    $script->section('section-1')->add(['role' => 'user', 'content' => 'content-3 {key-1}']);

    $script->section('section-2')->add(['role' => 'user', 'content' => 'content-4']);
    $script->section('section-2')->add(['role' => 'assistant', 'content' => 'content-5']);
    $script->section('section-2')->add(['role' => 'user', 'content' => 'content-6 {key-2}']);

    $messages = $script->select(['section-2', 'section-1'])->toNativeArray(ClientType::Cohere, ['section-2', 'section-1']);

    expect(count($messages))->toBe(6);
    expect($messages[0]['role'])->toBe('USER');
    expect($messages[0]['message'])->toBe('content-4');
    expect($messages[1]['role'])->toBe('CHATBOT');
    expect($messages[1]['message'])->toBe('content-5');
    expect($messages[2]['role'])->toBe('USER');
    expect($messages[2]['message'])->toBe('content-6 value-2');
    expect($messages[3]['role'])->toBe('USER');
    expect($messages[3]['message'])->toBe('content-1');
    expect($messages[4]['role'])->toBe('CHATBOT');
    expect($messages[4]['message'])->toBe('content-2');
    expect($messages[5]['role'])->toBe('USER');
    expect($messages[5]['message'])->toBe('content-3 value-1');
});

it('translates messages to native format - Anthropic', function () {
    $script = new Script(
        new Section('section-1'),
        new Section('section-2'),
    );
    $script->setContext([
        'key-1' => 'value-1',
        'key-2' => 'value-2',
    ]);
    $script->section('section-1')->add(['role' => 'user', 'content' => 'content-1']);
    $script->section('section-1')->add(['role' => 'assistant', 'content' => 'content-2']);
    $script->section('section-1')->add(['role' => 'user', 'content' => 'content-3 {key-1}']);

    $script->section('section-2')->add(['role' => 'user', 'content' => 'content-4']);
    $script->section('section-2')->add(['role' => 'assistant', 'content' => 'content-5']);
    $script->section('section-2')->add(['role' => 'user', 'content' => 'content-6 {key-2}']);

    $messages = $script->select(['section-2', 'section-1'])->toNativeArray(ClientType::Anthropic);

    expect(count($messages))->toBe(6);
    expect($messages[0]['role'])->toBe('user');
    expect($messages[0]['content'])->toBe('content-4');
    expect($messages[1]['role'])->toBe('assistant');
    expect($messages[1]['content'])->toBe('content-5');
    expect($messages[2]['role'])->toBe('user');
    expect($messages[2]['content'])->toBe('content-6 value-2');
    expect($messages[3]['role'])->toBe('user');
    expect($messages[3]['content'])->toBe('content-1');
    expect($messages[4]['role'])->toBe('assistant');
    expect($messages[4]['content'])->toBe('content-2');
    expect($messages[5]['role'])->toBe('user');
    expect($messages[5]['content'])->toBe('content-3 value-1');
});

it('translates messages to string', function () {
    $script = new Script(
        new Section('section-1'),
        new Section('section-2'),
    );
    $script->setContext([
        'key-1' => 'value-1',
        'key-2' => 'value-2',
    ]);
    $script->section('section-1')->add(['role' => 'user', 'content' => 'content-1']);
    $script->section('section-1')->add(['role' => 'assistant', 'content' => 'content-2']);
    $script->section('section-1')->add(['role' => 'user', 'content' => 'content-3 {key-1}']);

    $script->section('section-2')->add(['role' => 'user', 'content' => 'content-4']);
    $script->section('section-2')->add(['role' => 'assistant', 'content' => 'content-5']);
    $script->section('section-2')->add(['role' => 'user', 'content' => 'content-6 {key-2}']);

    $text = $script->select(['section-2', 'section-1'])->toString();
    expect($text)->toBe("content-4\ncontent-5\ncontent-6 value-2\ncontent-1\ncontent-2\ncontent-3 value-1\n");

    $text = $script->select('section-1')->toString();
    expect($text)->toBe("content-1\ncontent-2\ncontent-3 value-1\n");
});