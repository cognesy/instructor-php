<?php

use Cognesy\Utils\Json\Json;

describe('Json Class', function () {
    it('creates an empty Json object', function () {
        $json = new Json();
        expect($json->isEmpty())->toBeTrue();
        expect($json->toString())->toBe('');
        expect($json->toArray())->toBe([]);
    });

    it('creates a Json object from a valid JSON string', function () {
        $validJson = '{"name":"John","age":30}';
        $json = Json::fromString($validJson);
        expect($json->isEmpty())->toBeFalse();
        expect($json->toString())->toBe($validJson);
        expect($json->toArray())->toBe(['name' => 'John', 'age' => 30]);
    });

    it('handles empty input gracefully', function () {
        $json = Json::fromString('');
        expect($json->isEmpty())->toBeTrue();
        expect($json->toString())->toBe('');
        expect($json->toArray())->toBe([]);
    });

    it('handles known cases', function ($json, $result) {
        expect(Json::fromString($json)->toArray())->toMatchArray($result);
    })->with([
        ['{"name": "John", "age": 30}', ["name"=>"John", "age"=>30]],
        ['{"name": "John", "age": 30} Other text', ["name"=>"John", "age"=>30]],
        ['Other text {"name": "John", "age": 30}', ["name"=>"John", "age"=>30]],
        ['Other text {"name": "John", "age": 30} Other text', ["name"=>"John", "age"=>30]],
        ['Other text {"name": "John", "age": 30} Other text {"name": "Jane", "age": 25}', ["name"=>"John", "age"=>30]],
    ]);

    it('handles known ACME cases', function ($json, $result) {
        expect(Json::fromString($json)->toArray())->toMatchArray($result);
    })->with([
        ['{"name":"ACME","year":2020}', ["name"=>"ACME","year"=>2020]],
        ['{"name":"ACME","year":2020} Other text', ["name"=>"ACME","year"=>2020]],
        ['Other text {"name":"ACME","year":2020}', ["name"=>"ACME","year"=>2020]],
        ['Other text {"name":"ACME","year":2020} Other text', ["name"=>"ACME","year"=>2020]],
        ['{\n  "year": 2020,\n  "name": "ACME"\n}', ["name"=>"ACME","year"=>2020]],
        ['{\n  "name": "ACME",\n  "year": 2020\n}\n```', ["name"=>"ACME","year"=>2020]],
        ['```json\n{\n  "name": "ACME",\n  "year": 2020\n}\n```', ["name"=>"ACME","year"=>2020]],
        ['```json\n{\n  "name": "ACME",\n  "year": 2020\n}', ["name"=>"ACME","year"=>2020]],
        ['{\n"name": "ACME",\n"year": 2020\n}\n```', ["name"=>"ACME","year"=>2020]],
        ['```json\n{\n"name": "ACME",\n"year": 2020\n}\n```', ["name"=>"ACME","year"=>2020]],
        ['```json\n{\n  "name": "ACME",\n  "year": 2020\n}\n```', ["name"=>"ACME","year"=>2020]],
        ['\n{\n"name": "ACME",\n"year": 2020\n}\n```', ["name"=>"ACME","year"=>2020]],
        ['\n{\t  "name": "ACME",\n  "year": 2020\n}\n```', ["name"=>"ACME","year"=>2020]],
        ['```json\n{"year": 2020, "name": "ACME"}\n```', ["name"=>"ACME","year"=>2020]],
    ]);

    it('extracts JSON from markdown', function () {
        $markdown = "# Title\n```json\n{\"key\": \"value\"}\n```\nOther text";
        $json = Json::fromString($markdown);
        expect($json->toArray())->toMatchArray(["key" => "value"]);
    });

    it('extracts JSON from text with brackets', function () {
        $text = "Some text before {\"key\": \"value\"} and after";
        $json = Json::fromString($text);
        expect($json->toArray())->toMatchArray(["key" => "value"]);
    });

    it('returns empty string for input without valid JSON', function () {
        $text = "No JSON here";
        $json = Json::fromString($text);
        expect($json->isEmpty())->toBeTrue();
    });

    it('parses partial JSON using fromPartial method', function () {
        $partialJson = '{"name": "John", "age":';
        $json = Json::fromPartial($partialJson);
        expect($json->toString())->toBe('{"name":"John","age":null}');
    });

    it('handles streaming partial JSON', function () {
        $streamParts = [
            '{"name": "Jo',
            'hn", "age": 3',
            '0, "city": "New York"}'
        ];

        $result = '';
        foreach ($streamParts as $part) {
            $result .= $part;
            $json = Json::fromPartial($result);
            expect($json->isEmpty())->toBeFalse();
        }

        expect($json->toArray())->toBe([
            'name' => 'John',
            'age' => 30,
            'city' => 'New York'
        ]);
    });

    it('handles JSON with nested structures', function () {
        $complexJson = '{"person": {"name": "John", "address": {"city": "New York", "zip": "10001"}}, "hobbies": ["reading", "swimming"]}';
        $json = Json::fromString($complexJson);
        expect($json->toArray())->toBe([
            'person' => [
                'name' => 'John',
                'address' => [
                    'city' => 'New York',
                    'zip' => '10001'
                ]
            ],
            'hobbies' => ['reading', 'swimming']
        ]);
    });

    it('parses JSON using static parse method', function () {
        $validJson = '{"name": "John", "age": 30}';
        $result = Json::decode($validJson);
        expect($result)->toBe(['name' => 'John', 'age' => 30]);
    });

    it('returns default value when parsing invalid JSON', function () {
        $invalidJson = '{"name": "John", "age": }';
        $result = Json::decode($invalidJson, ['default' => true]);
        expect($result)->toBe(['default' => true]);
    });

    it('encodes data to JSON string', function () {
        $data = ['name' => 'John', 'age' => 30];
        $result = Json::encode($data);
        expect($result)->toBe('{"name":"John","age":30}');
    });

    it('handles unicode characters correctly', function () {
        $unicodeJson = '{"name":"Jöhn","city":"Münich"}';
        $json = Json::fromString($unicodeJson);
        expect($json->toString())->toBe('{"name":"J\u00f6hn","city":"M\u00fcnich"}');
        expect($json->toArray())->toBe(['name' => 'Jöhn', 'city' => 'Münich']);
    });

    it('handles large JSON payloads', function () {
        $largeArray = array_fill(0, 1000, ['id' => uniqid(), 'data' => str_repeat('a', 100)]);
        $largeJson = json_encode($largeArray);
        $json = Json::fromString($largeJson);
        expect(count($json->toArray()))->toBe(1000);
    });

    it('extracts valid JSON from a string with multiple JSON-like structures', function () {
        $mixedText = 'Invalid {"key": "value"} Valid {"name": "John", "age": 30} Another {"x": 1}';
        $json = Json::fromString($mixedText);
        expect($json->toArray())->toBe(['key' => 'value']);
    });

    it('preserves numeric values correctly', function () {
        $numericJson = '{"integer": 42, "float": 3.14, "exponential": 1.23e-4}';
        $json = Json::fromString($numericJson);
        $array = $json->toArray();

        expect($array['integer'])->toBe(42);
        expect($array['float'])->toBe(3.14);
        expect($array['exponential'])->toBe(1.23e-4);
    });

    it('handles boolean and null values correctly', function () {
        $mixedJson = '{"bool_true": true, "bool_false": false, "null_value": null}';
        $json = Json::fromString($mixedJson);
        $array = $json->toArray();

        expect($array['bool_true'])->toBeTrue();
        expect($array['bool_false'])->toBeFalse();
        expect($array['null_value'])->toBeNull();
    });

    it('preserves array structures', function () {
        $arrayJson = '{"numbers": [1, 2, 3], "mixed": [1, "two", true, null]}';
        $json = Json::fromString($arrayJson);
        $array = $json->toArray();

        expect($array['numbers'])->toBe([1, 2, 3]);
        expect($array['mixed'])->toBe([1, "two", true, null]);
    });

    it('ignores comments in JSON-like strings', function () {
        $jsonWithComments = '
        {
            // This is a comment
            "name": "John", /* Multi-line
            comment */
            "age": 30
        }';
        $json = Json::fromString($jsonWithComments);
        expect($json->toArray())->toBe(['name' => 'John', 'age' => 30]);
    })->skip("Not supported yet");

    it('handles multiple JSON-like strings and returns the first valid one', function () {
        $text = "{\"invalid\": \"json\" {\"valid\": \"json\"}";
        $json = Json::fromString($text);
        expect($json->toString())->toBe('{"valid": "json"}');
    })->skip("Not supported yet");

    it('gracefully handles malformed JSON with extra commas', function () {
        $malformedJson = '{"name": "John",, "age": 30,}';
        $json = Json::fromPartial($malformedJson);
        expect($json->toArray())->toBe(['name' => 'John', 'age' => 30]);
    })->skip("Not supported yet");

    it('handles escaped characters in JSON strings', function () {
        $escapedJson = '{"message": "Hello \"World\"! \\ \/ \b \f \n \r \t"}';
        $json = Json::fromString($escapedJson);

        // Test that the parsed array contains the correct unescaped string
        expect($json->toArray()['message'])->toBe("Hello \"World\"! \\ / \b \f \n \r \t");

        // Test that when re-encoded, the JSON is equivalent to the original
        $reEncodedJson = json_encode($json->toArray());
        expect(json_decode($reEncodedJson, true))->toBe(json_decode($escapedJson, true));
    })->skip("Not supported yet");
});