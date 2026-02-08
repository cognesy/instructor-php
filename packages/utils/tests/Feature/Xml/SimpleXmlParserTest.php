<?php

use Cognesy\Utils\Xml\SimpleXmlParser;

it('converts XML to array with attributes', function () {
    $xmlString = '<root attr="value"><child>content</child></root>';
    $xml = SimpleXmlParser::from($xmlString)->withAttributes();
    $expected = [
        '_attributes' => ['attr' => 'value'],
        'child' => 'content'
    ];
    expect($xml->toArray())->toEqual($expected);
});

it('converts XML to array without attributes', function () {
    $xmlString = '<root><child>content</child></root>';
    $xml = SimpleXmlParser::from($xmlString);
    $expected = ['child' => 'content'];
    expect($xml->toArray())->toEqual($expected);
});

it('includes root element in array', function () {
    $xmlString = '<root><child>content</child></root>';
    $xml = SimpleXmlParser::from($xmlString)->withRoot();
    $expected = ['root' => ['child' => 'content']];
    expect($xml->toArray())->toEqual($expected);
});

it('converts names to snake_case', function () {
    $xmlString = '<rootElement><childElement>content</childElement></rootElement>';
    $xml = SimpleXmlParser::from($xmlString)->withNaming('snake');
    $expected = ['child_element' => 'content'];
    expect($xml->toArray())->toEqual($expected);
});

it('converts names to camelCase', function () {
    $xmlString = '<root_element><child_element>content</child_element></root_element>';
    $xml = SimpleXmlParser::from($xmlString)->withNaming('camel');
    $expected = ['childElement' => 'content'];
    expect($xml->toArray())->toEqual($expected);
});

it('handles empty XML string', function () {
    $xmlString = '';
    $xml = SimpleXmlParser::from($xmlString);
    $expected = [];
    expect($xml->toArray())->toEqual($expected);
});

it('handles XML with multiple children', function () {
    $xmlString = '<root><child>content1</child><child>content2</child></root>';
    $xml = SimpleXmlParser::from($xmlString);
    $expected = ['child' => ['content1', 'content2']];
    expect($xml->toArray())->toEqual($expected);
});

it('handles XML with multiple children with the same name', function () {
    $xmlString = '<root><child>content1</child><child>content2</child></root>';
    $xml = SimpleXmlParser::from($xmlString)->withNaming('snake');
    $expected = ['child' => ['content1', 'content2']];
    expect($xml->toArray())->toEqual($expected);
});

it('handles CDATA in XML', function () {
    $xmlString = '<root><child><![CDATA[<child>content</child>]]></child></root>';
    $xml = SimpleXmlParser::from($xmlString)->withRoot();
    $expected = ['root' => ['child' => '<child>content</child>']];
    expect($xml->toArray())->toEqual($expected);
});

it('throws exception for invalid XML', function () {
    $xmlString = '<root><child>content</child>';
    expect(fn() => SimpleXmlParser::from($xmlString)->toArray())->toThrow(Exception::class);
});

it('handles attributes with special characters in XML', function () {
    $xmlString = '<root attr="value &amp; more"><child>content</child></root>';
    $xml = SimpleXmlParser::from($xmlString)->withAttributes();
    $expected = [
        '_attributes' => ['attr' => 'value & more'],
        'child' => 'content'
    ];
    expect($xml->toArray())->toEqual($expected);
});

it('wraps XML string with specified root element', function () {
    $xmlString = '<child>content</child>';
    $xml = SimpleXmlParser::from($xmlString)->withRoot()->wrapped('wrapper');
    $expected = ['wrapper' => ['child' => 'content']];
    expect($xml->toArray())->toEqual($expected);
});

it('handles special characters in XML', function () {
    $xmlString = '<root><child>content &amp; more content</child></root>';
    $xml = SimpleXmlParser::from($xmlString);
    $expected = ['child' => 'content & more content'];
    expect($xml->toArray())->toEqual($expected);
});

it('handles empty elements in XML', function () {
    $xmlString = '<root><child /></root>';
    $xml = SimpleXmlParser::from($xmlString);
    $expected = ['child' => []];
    expect($xml->toArray())->toEqual($expected);
});

it('handles nested elements in XML', function () {
    $xmlString = '<root><parent><child>content</child></parent></root>';
    $xml = SimpleXmlParser::from($xmlString);
    $expected = ['parent' => ['child' => 'content']];
    expect($xml->toArray())->toEqual($expected);
});

it('handles mixed content in XML', function () {
    $xmlString = '<root>text<child>content</child>more text</root>';
    $xml = SimpleXmlParser::from($xmlString)->withRoot()->toArray();
    $expected = ['root' => ['textmore text', ['child' => 'content']]];
    expect($xml)->toEqual($expected);
});
