<?php

use Cognesy\Instructor\Utils\Template;

test('it renders a simple string', function () {
    $template = 'Hello, <|name|>!';
    $context = ['name' => 'John'];

    $rendered = Template::render($template, $context);

    expect($rendered)->toBe('Hello, John!');
});

test('it handles missing keys gracefully', function () {
    $template = 'Hello, <|name|>! Your score is <|score|>.';
    $context = ['name' => 'John'];

    $rendered = Template::render($template, $context);

    expect($rendered)->toBe('Hello, John! Your score is .');
});

test('it renders an array of messages', function () {
    $messages = [
        ['role' => 'user', 'content' => 'Hello, <|name|>!'],
        ['role' => 'assistant', 'content' => 'Hi there, <|name|>!'],
    ];
    $context = ['name' => 'John'];

    $rendered = (new Template($context))->renderMessages($messages);

    expect($rendered)->toBe([
        ['role' => 'user', 'content' => 'Hello, John!'],
        ['role' => 'assistant', 'content' => 'Hi there, John!'],
    ]);
});

test('it renders an array of objects', function () {
    $objects = [
        ['content' => 'Hello, <|name|>!'],
        ['content' => 'Hi there, <|name|>!'],
    ];
    $context = ['name' => 'John'];

    $rendered = (new Template($context))->renderArray($objects);

    expect($rendered)->toBe([
        'Hello, John!',
        'Hi there, John!',
    ]);
});

test('it handles complex context values', function () {
    $template = 'Name: <|name|>, Age: <|age|>, Tags: <|tags|>';
    $context = [
        'name' => 'John',
        'age' => 30,
        'tags' => ['developer', 'musician'],
    ];

    $rendered = Template::render($template, $context);

    expect($rendered)->toBe('Name: John, Age: 30, Tags: ["developer","musician"]');
});
