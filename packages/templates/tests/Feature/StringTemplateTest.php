<?php

use Cognesy\Template\Utils\StringTemplate;

test('it renders a simple string', function () {
    $template = 'Hello, <|name|>!';
    $parameters = ['name' => 'John'];

    $rendered = StringTemplate::render($template, $parameters);

    expect($rendered)->toBe('Hello, John!');
});

test('it handles missing keys gracefully', function () {
    $template = 'Hello, <|name|>! Your score is <|score|>.';
    $parameters = ['name' => 'John'];

    $rendered = StringTemplate::render($template, $parameters);

    expect($rendered)->toBe('Hello, John! Your score is .');
});

test('it renders an array of messages', function () {
    $messages = [
        ['role' => 'user', 'content' => 'Hello, <|name|>!'],
        ['role' => 'assistant', 'content' => 'Hi there, <|name|>!'],
    ];
    $parameters = ['name' => 'John'];

    $rendered = (new StringTemplate($parameters))->renderMessages($messages);

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
    $parameters = ['name' => 'John'];

    $rendered = (new StringTemplate($parameters))->renderArray($objects);

    expect($rendered)->toBe([
        'Hello, John!',
        'Hi there, John!',
    ]);
});

test('it handles complex context values', function () {
    $template = 'Name: <|name|>, Age: <|age|>, Tags: <|tags|>';
    $parameters = [
        'name' => 'John',
        'age' => 30,
        'tags' => ['developer', 'musician'],
    ];

    $rendered = StringTemplate::render($template, $parameters);

    expect($rendered)->toBe('Name: John, Age: 30, Tags: ["developer","musician"]');
});
