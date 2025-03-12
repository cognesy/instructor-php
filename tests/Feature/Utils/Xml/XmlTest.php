<?php

use Cognesy\Utils\Xml\Xml;

it('converts XML to array with attributes', function () {
    $xmlString = '<root attr="value"><child>sample content</child></root>';
    $xml = Xml::from($xmlString)->withTags(['root','child'])->toXmlElement();
    expect($xml->attribute('attr'))->toEqual('value');
    expect($xml->first('child')?->content())->toEqual('sample content');
});

it('converts XML to array without attributes', function () {
    $xmlString = '<root><child>content</child></root>';
    $xml = Xml::from($xmlString)->withTags(['root','child'])->toXmlElement();
    expect($xml->first('child')?->content())->toEqual('content');
});

it('includes root element in array', function () {
    $xmlString = '<root><child>content</child></root>';
    $xml = Xml::from($xmlString)->withTags(['root','child'])->toXmlElement();
    expect($xml->tag())->toEqual('root');
});

it('handles empty XML string', function () {
    $xmlString = '';
    $xml = Xml::from($xmlString)->toArray();
    $expected = [
        'tag' => '',
        'content' => '',
        'attributes' => [],
        'children' => [],
    ];
    expect($xml)->toEqual($expected);
});

it('handles XML with multiple children', function () {
    $xmlString = '<root><child>content1</child><child>content2</child></root>';
    $xml = Xml::from($xmlString)->withTags(['root','child'])->toXmlElement();
    expect($xml->all('child'))->toHaveLength(2);
});

it('handles CDATA in XML', function () {
    $xmlString = '<root><child><![CDATA[<child>content</child>]]></child></root>';
    $xml = Xml::from($xmlString)->withTags(['root','child'])->toXmlElement();
    expect($xml->first('child')->content())->toEqual('<child>content</child>');
});

it('throws exception for invalid XML', function () {
    $xmlString = '<root><child>content</child>';
    $xml = Xml::from($xmlString)->withTags(['root','child']);
    expect(fn() => $xml->toArray())->toThrow(Exception::class);
});

it('handles attributes with special characters in XML', function () {
    $xmlString = '<root attr="value &amp; more"><child>content</child></root>';
    $xml = Xml::from($xmlString)->withTags(['root','child'])->toXmlElement();
    expect($xml->attribute('attr'))->toEqual('value & more');
});

it('wraps XML string with specified root element', function () {
    $xmlString = '<child>content</child>';
    $xml = Xml::from($xmlString)->withTags(['wrapper', 'child'])->wrapped('wrapper')->toXmlElement();
    expect($xml->tag())->toEqual('wrapper');
    expect($xml->first('child')->content())->toEqual('content');
});

it('handles special characters in XML', function () {
    $xmlString = '<root><child>content &amp; more content</child></root>';
    $xml = Xml::from($xmlString)->withTags(['root','child'])->toXmlElement();
    expect($xml->first('child')->content())->toEqual('content & more content');
});

it('handles empty elements in XML', function () {
    $xmlString = '<root><child /></root>';
    $xml = Xml::from($xmlString)->toXmlElement();
    expect($xml->first('child')->content())->toEqual('');
});

it('handles selective parsing', function () {
    $xmlString = '<root><child/></root>';
    $xml = Xml::from($xmlString)->withTags(['root'])->toXmlElement();
    expect($xml->content())->toEqual('<child/>');
});

it('handles selective parsing with nested tags', function () {
    $xmlString = '<root><child><tag>xxx</tag></child></root>';
    $xml = Xml::from($xmlString)->withTags(['root', 'child'])->toXmlElement();
    expect($xml->first('child')->content())->toEqual('<tag>xxx</tag>');
});

it('handles nested elements in XML', function () {
    $xmlString = '<root><parent><child>content</child></parent></root>';
    $xml = Xml::from($xmlString)->toXmlElement();
    expect($xml->first('parent')->first('child')->content())->toEqual('content');
});

it('handles mixed content in XML', function () {
    $xmlString = '<root>text<child>content</child>more text</root>';
    $xml = Xml::from($xmlString)->toXmlElement();
    expect($xml->content())->toEqual('textmore text');
});

