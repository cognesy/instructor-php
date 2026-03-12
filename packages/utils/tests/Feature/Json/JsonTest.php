<?php declare(strict_types=1);

use Cognesy\Utils\Json\Json;

describe('Json value object', function () {
    it('creates empty via none()', function () {
        $json = Json::none();
        expect($json->isEmpty())->toBeTrue();
        expect($json->toString())->toBe('');
        expect($json->toArray())->toBe([]);
    });

    it('creates from empty string', function () {
        $json = Json::fromString('');
        expect($json->isEmpty())->toBeTrue();
        expect($json->toString())->toBe('');
        expect($json->toArray())->toBe([]);
    });

    // -- Lazy behavior --

    it('fromString stores string without decoding', function () {
        $original = '{"a":1}';
        $json = Json::fromString($original);
        // toString returns original string without re-encoding
        expect($json->toString())->toBe($original);
    });

    it('fromString preserves whitespace in original string', function () {
        $original = '{"a" : 1}';
        $json = Json::fromString($original);
        expect($json->toString())->toBe($original);
    });

    it('fromArray stores array without encoding', function () {
        $array = ['a' => 1];
        $json = Json::fromArray($array);
        expect($json->toArray())->toBe($array);
    });

    it('fromArray lazily encodes on toString', function () {
        $json = Json::fromArray(['a' => 1]);
        expect($json->toString())->toBe('{"a":1}');
    });

    it('fromString lazily decodes on toArray', function () {
        $json = Json::fromString('{"a":1}');
        expect($json->toArray())->toBe(['a' => 1]);
    });

    // -- fromPartial --

    it('parses partial JSON with trailing comma', function () {
        $json = Json::fromPartial('{"name":"John","age":30,}');
        expect($json->toArray())->toBe(['name' => 'John', 'age' => 30]);
    });

    it('parses partial JSON with unclosed brace', function () {
        $json = Json::fromPartial('{"name":"John","age":30');
        expect($json->toArray())->toMatchArray(['name' => 'John', 'age' => 30]);
    });

    it('parses partial JSON with unclosed string', function () {
        $json = Json::fromPartial('{"name":"John');
        expect($json->toArray())->toMatchArray(['name' => 'John']);
    });

    it('handles streaming partial JSON accumulation', function () {
        $parts = ['{"name": "Jo', 'hn", "age": 3', '0, "city": "New York"}'];
        $result = '';
        foreach ($parts as $part) {
            $result .= $part;
            $json = Json::fromPartial($result);
            expect($json->isEmpty())->toBeFalse();
        }
        expect($json->toArray())->toBe(['name' => 'John', 'age' => 30, 'city' => 'New York']);
    });

    // -- Immutability --

    it('is immutable - multiple toArray calls return same result', function () {
        $json = Json::fromString('{"a":1}');
        $first = $json->toArray();
        $second = $json->toArray();
        expect($first)->toBe($second);
    });

    // -- Complex structures --

    it('handles nested structures', function () {
        $complex = '{"person":{"name":"John","address":{"city":"New York","zip":"10001"}},"hobbies":["reading","swimming"]}';
        $json = Json::fromString($complex);
        expect($json->toArray())->toBe([
            'person' => ['name' => 'John', 'address' => ['city' => 'New York', 'zip' => '10001']],
            'hobbies' => ['reading', 'swimming'],
        ]);
    });

    it('preserves numeric values', function () {
        $json = Json::fromString('{"integer":42,"float":3.14,"exponential":1.23e-4}');
        $array = $json->toArray();
        expect($array['integer'])->toBe(42);
        expect($array['float'])->toBe(3.14);
        expect($array['exponential'])->toBe(1.23e-4);
    });

    it('handles boolean and null values', function () {
        $json = Json::fromString('{"t":true,"f":false,"n":null}');
        $array = $json->toArray();
        expect($array['t'])->toBeTrue();
        expect($array['f'])->toBeFalse();
        expect($array['n'])->toBeNull();
    });

    it('preserves array structures', function () {
        $json = Json::fromString('{"numbers":[1,2,3],"mixed":[1,"two",true,null]}');
        $array = $json->toArray();
        expect($array['numbers'])->toBe([1, 2, 3]);
        expect($array['mixed'])->toBe([1, "two", true, null]);
    });

    it('handles escaped characters', function () {
        $escaped = '{"message":"Hello \\"World\\"! \\\\ \\/ \\b \\f \\n \\r \\t"}';
        $json = Json::fromString($escaped);
        $expected = json_decode($escaped, true)['message'];
        expect($json->toArray()['message'])->toBe($expected);
    });

    it('handles large JSON payloads', function () {
        $largeArray = array_fill(0, 1000, ['id' => 'x', 'data' => str_repeat('a', 100)]);
        $largeJson = json_encode($largeArray);
        $json = Json::fromString($largeJson);
        expect(count($json->toArray()))->toBe(1000);
    });

    // -- Static helpers --

    it('encode encodes to JSON string', function () {
        expect(Json::encode(['a' => 1]))->toBe('{"a":1}');
    });

    it('encode throws on unencodable data', function () {
        expect(fn() => Json::encode(["bad" => "\xB1\x31"]))
            ->toThrow(InvalidArgumentException::class, 'Failed to encode JSON');
    });

    it('fromArray throws on unencodable data', function () {
        // fromArray defers encoding, so it fails on toString()
        $json = Json::fromArray(["bad" => "\xB1\x31"]);
        expect(fn() => $json->toString())
            ->toThrow(InvalidArgumentException::class, 'Failed to encode JSON');
    });

    it('decode parses valid JSON', function () {
        expect(Json::decode('{"a":1}'))->toBe(['a' => 1]);
    });

    it('decode returns default on invalid JSON', function () {
        expect(Json::decode('{"broken', ['default' => true]))->toBe(['default' => true]);
    });

    it('decode throws without default on invalid JSON', function () {
        expect(fn() => Json::decode('{"broken'))->toThrow(JsonException::class);
    });

    // -- Falsy value regression --

    it('decode preserves falsy values with default', function () {
        expect(Json::decode('0', 'DEF'))->toBe(0);
        expect(Json::decode('false', 'DEF'))->toBeFalse();
        expect(Json::decode('[]', ['default' => true]))->toBe([]);
        expect(Json::decode('""', 'DEF'))->toBe('');
        expect(Json::decode('null', 'DEF'))->toBeNull();
    });

    it('decode preserves falsy values without default', function () {
        expect(Json::decode('0'))->toBe(0);
        expect(Json::decode('false'))->toBeFalse();
        expect(Json::decode('[]'))->toBe([]);
        expect(Json::decode('""'))->toBe('');
        expect(Json::decode('null'))->toBeNull();
    });

    // -- fromString throws on invalid JSON --

    it('fromString throws on invalid JSON when toArray is called', function () {
        $json = Json::fromString('not json');
        expect(fn() => $json->toArray())->toThrow(InvalidArgumentException::class);
    });

    // -- format --

    it('format returns formatted JSON', function () {
        $json = Json::fromArray(['a' => 1]);
        $formatted = $json->format(JSON_PRETTY_PRINT);
        expect($formatted)->toContain("\n");
        expect(json_decode($formatted, true))->toBe(['a' => 1]);
    });
});
