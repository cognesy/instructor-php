<?php declare(strict_types=1);

use Cognesy\Utils\Json\JsonDecoder;

describe('JsonDecoder', function () {
    // -- Fast path (valid JSON) --

    it('decodes valid JSON via fast path', function () {
        expect(JsonDecoder::decode('{"a":1}'))->toBe(['a' => 1]);
    });

    it('decodes valid nested JSON', function () {
        expect(JsonDecoder::decode('{"outer":{"inner":"value"}}'))
            ->toBe(['outer' => ['inner' => 'value']]);
    });

    it('decodes valid arrays', function () {
        expect(JsonDecoder::decode('["a","b"]'))->toBe(['a', 'b']);
    });

    it('decodes numbers correctly', function () {
        $result = JsonDecoder::decode('{"integer":42,"float":3.14,"exponent":1.23e-4,"negative":-10}');
        expect($result)->toBe([
            'integer' => 42,
            'float' => 3.14,
            'exponent' => 1.23e-4,
            'negative' => -10,
        ]);
    });

    it('decodes booleans and null', function () {
        expect(JsonDecoder::decode('{"t":true,"f":false,"n":null}'))
            ->toBe(['t' => true, 'f' => false, 'n' => null]);
    });

    it('returns null for empty input', function () {
        expect(JsonDecoder::decode(''))->toBeNull();
        expect(JsonDecoder::decode('   '))->toBeNull();
    });

    // -- Repair path --

    it('handles trailing comma', function () {
        expect(JsonDecoder::decode('{"a":1,}'))->toBe(['a' => 1]);
    });

    it('handles trailing comma in array', function () {
        expect(JsonDecoder::decode('[1,2,3,]'))->toBe([1, 2, 3]);
    });

    it('handles unclosed brace', function () {
        $result = JsonDecoder::decode('{"a":1');
        expect($result)->toBeArray();
        expect($result['a'])->toBe(1);
    });

    it('handles unclosed bracket', function () {
        $result = JsonDecoder::decode('[1,2,3');
        expect($result)->toBeArray();
        expect($result)->toContain(1, 2, 3);
    });

    it('handles unclosed string', function () {
        $result = JsonDecoder::decode('{"a":"hello');
        expect($result)->toBeArray();
        expect($result['a'])->toBe('hello');
    });

    it('handles multiple unclosed levels', function () {
        $result = JsonDecoder::decode('{"a":{"b":"nested');
        expect($result)->toBeArray();
        expect($result['a'])->toBeArray();
        expect($result['a']['b'])->toBe('nested');
    });

    // -- Lenient parser path --

    it('handles partial literal true', function () {
        $result = JsonDecoder::decode('{"a":tr');
        expect($result)->toBeArray();
        expect($result['a'])->toBeTrue();
    });

    it('handles partial literal false', function () {
        $result = JsonDecoder::decode('{"a":fal');
        expect($result)->toBeArray();
        expect($result['a'])->toBeFalse();
    });

    it('handles partial literal null', function () {
        $result = JsonDecoder::decode('{"a":nu');
        expect($result)->toBeArray();
        expect($result['a'])->toBeNull();
    });

    it('handles double trailing commas', function () {
        expect(JsonDecoder::decode('{"name":"John",,"age":30,}'))
            ->toBe(['name' => 'John', 'age' => 30]);
    });

    // -- decodeToArray --

    it('decodeToArray returns array for valid JSON', function () {
        expect(JsonDecoder::decodeToArray('{"a":1}'))->toBe(['a' => 1]);
    });

    it('decodeToArray returns empty array for empty input', function () {
        expect(JsonDecoder::decodeToArray(''))->toBe([]);
    });

    it('decodeToArray returns empty array for scalar', function () {
        expect(JsonDecoder::decodeToArray('"just a string"'))->toBe([]);
    });

    // -- Complex structures --

    it('handles complex nested structure', function () {
        $json = '{"name":"John","age":30,"courses":["Math","Physics"],"address":{"city":"Anytown","zip":"12345"}}';
        $result = JsonDecoder::decode($json);
        expect($result)->toBe([
            'name' => 'John',
            'age' => 30,
            'courses' => ['Math', 'Physics'],
            'address' => ['city' => 'Anytown', 'zip' => '12345'],
        ]);
    });

    it('handles deeply nested structure', function () {
        $json = '{"l1":{"l2":{"l3":{"l4":{"l5":"deep"}}}}}';
        expect(JsonDecoder::decode($json))
            ->toBe(['l1' => ['l2' => ['l3' => ['l4' => ['l5' => 'deep']]]]]);
    });

    it('handles escaped characters in strings', function () {
        $json = '{"escaped":"This is a \\"quoted\\" string with \\t tab and \\n newline"}';
        $result = JsonDecoder::decode($json);
        expect($result['escaped'])->toBe("This is a \"quoted\" string with \t tab and \n newline");
    });

    // -- Partial JSON fragments --

    it('handles partial JSON with key only', function () {
        $result = JsonDecoder::decode('{"field-a":"str-1", "field');
        expect($result)->toMatchArray(['field-a' => 'str-1']);
    });

    it('handles partial JSON with array mid-stream', function () {
        $result = JsonDecoder::decode('{"field-a":"str-1", "field-b":[1,2');
        expect($result)->toBeArray();
        expect($result['field-a'])->toBe('str-1');
        expect($result['field-b'])->toContain(1, 2);
    });

    // -- JSON preceded by text --

    it('handles JSON preceded by non-JSON text', function () {
        $result = JsonDecoder::decode('Some text {"a":1}');
        expect($result)->toBe(['a' => 1]);
    });
});
