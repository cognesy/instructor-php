<?php

use Cognesy\Utils\Xml\SelectiveXmlParser;

it('parses a simple XML structure correctly', function () {
    $parser = new SelectiveXmlParser(['chat', 'message', 'user', 'assistant']);
    $xml = '<chat><message role="system">Hello!</message></chat>';

    $expected = [[
        'tag' => 'chat',
        'content' => '',
        'attributes' => [],
        'children' => [
            [
                'tag' => 'message',
                'content' => 'Hello!',
                'attributes' => ['role' => 'system'],
                'children' => [],
            ]
        ],
    ]];

    $result = $parser->parse($xml);
    expect($result)->toEqual($expected);
});

it('handles attributes correctly', function () {
    $parser = new SelectiveXmlParser(['user']);
    $xml = '<user attribute="value1">Hello</user>';

    $expected = [[
        'tag' => 'user',
        'content' => 'Hello',
        'attributes' => ['attribute' => 'value1'],
        'children' => [],
    ]];

    $result = $parser->parse($xml);
    expect($result)->toEqual($expected);
});

it('handles nested tags correctly', function () {
    $parser = new SelectiveXmlParser(['chat', 'message', 'user']);
    $xml = '<chat><message>Outer message <user attribute="value">Inner message</user></message></chat>';

    $expected = [[
        'tag' => 'chat',
        'content' => '',
        'attributes' => [],
        'children' => [
            [
                'tag' => 'message',
                'content' => 'Outer message ',
                'attributes' => [],
                'children' => [
                    [
                        'tag' => 'user',
                        'content' => 'Inner message',
                        'attributes' => ['attribute' => 'value'],
                        'children' => [],
                    ]
                ],
            ]
        ],
    ]];

    $result = $parser->parse($xml);
    expect($result)->toEqual($expected);
});

it('handles empty elements correctly', function () {
    $parser = new SelectiveXmlParser(['chat', 'message']);
    $xml = '<chat><message /></chat>';

    $expected = [[
        'tag' => 'chat',
        'content' => '',
        'attributes' => [],
        'children' => [
            [
                'tag' => 'message',
                'content' => '',
                'attributes' => [],
                'children' => [],
            ]
        ],
    ]];

    $result = $parser->parse($xml);
    expect($result)->toEqual($expected);
});

it('handles unknown tags correctly', function () {
    $parser = new SelectiveXmlParser(['chat']);
    $xml = '<chat><unknown>Some unknown content</unknown></chat>';

    $expected = [[
        'tag' => 'chat',
        'content' => '<unknown>Some unknown content</unknown>',
        'attributes' => [],
        'children' => [],
    ]];

    $result = $parser->parse($xml);
    expect($result)->toEqual($expected);
});

it('handles mixed content and children correctly', function () {
    $parser = new SelectiveXmlParser(['chat', 'message', 'user']);
    $xml = '<chat><message>Text before<user>Inside user</user>Text after</message></chat>';

    $expected = [[
        'tag' => 'chat',
        'content' => '',
        'attributes' => [],
        'children' => [
            [
                'tag' => 'message',
                'content' => 'Text beforeText after',
                'attributes' => [],
                'children' => [
                    [
                        'tag' => 'user',
                        'content' => 'Inside user',
                        'attributes' => [],
                        'children' => [],
                    ]
                ],
            ]
        ],
    ]];

    $result = $parser->parse($xml);
    expect($result)->toEqual($expected);
});

it('throws exception for multiple root elements', function () {
    $parser = new SelectiveXmlParser(['root']);
    $xml = '<root>First</root><root>Second</root>';
    expect(fn() => $parser->parse($xml))
        ->toThrow(RuntimeException::class, 'Invalid XML');
});

it('allows single root with multiple children', function () {
    $parser = new SelectiveXmlParser(['root', 'child']);
    $xml = '<root><child>First</child><child>Second</child></root>';

    $result = $parser->parse($xml);
    expect($result)->toHaveCount(1);
    expect($result[0]['children'])->toHaveCount(2);
});