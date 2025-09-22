<?php

use Cognesy\Utils\Data\DataMap;

beforeEach(function () {
    $this->testData = [
        'name' => 'John Doe',
        'age' => 30,
        'address' => [
            'street' => '123 Main St',
            'city' => 'Anytown',
            'country' => 'USA'
        ],
        'hobbies' => ['reading', 'swimming'],
        'metadata' => [
            'created_at' => '2024-01-01',
            'updated_at' => '2024-01-15'
        ]
    ];
    $this->dataMap = new DataMap($this->testData);
});

describe('__isset magic method', function () {
    test('returns true for existing keys', function () {
        expect(isset($this->dataMap->name))->toBeTrue();
        expect(isset($this->dataMap->address))->toBeTrue();
        expect(isset($this->dataMap->hobbies))->toBeTrue();
    });

    test('returns false for non-existing keys', function () {
        expect(isset($this->dataMap->nonexistent))->toBeFalse();
        expect(isset($this->dataMap->missing))->toBeFalse();
    });
});

describe('merge method', function () {
    test('merges new data into existing DataMap', function () {
        $newData = [
            'email' => 'john@example.com',
            'phone' => '555-1234'
        ];

        $this->dataMap->merge($newData);

        expect($this->dataMap->get('email'))->toBe('john@example.com');
        expect($this->dataMap->get('phone'))->toBe('555-1234');
        expect($this->dataMap->get('name'))->toBe('John Doe'); // Original data preserved
    });

    test('overwrites existing values when merging', function () {
        $newData = [
            'name' => 'Jane Doe',
            'age' => 25
        ];

        $this->dataMap->merge($newData);

        expect($this->dataMap->get('name'))->toBe('Jane Doe');
        expect($this->dataMap->get('age'))->toBe(25);
    });

    test('merges nested arrays correctly', function () {
        $newData = [
            'address' => [
                'zip' => '12345',
                'state' => 'CA'
            ]
        ];

        $this->dataMap->merge($newData);

        expect($this->dataMap->get('address.zip'))->toBe('12345');
        expect($this->dataMap->get('address.state'))->toBe('CA');
        expect($this->dataMap->get('address.street'))->toBe('123 Main St'); // Original preserved
    });

    test('returns self for method chaining', function () {
        $result = $this->dataMap->merge(['test' => 'value']);

        expect($result)->toBe($this->dataMap);
        expect($result)->toBeInstanceOf(DataMap::class);
    });
});

describe('except method', function () {
    test('removes specified keys from DataMap', function () {
        $result = $this->dataMap->except('age', 'hobbies');

        expect($result->has('age'))->toBeFalse();
        expect($result->has('hobbies'))->toBeFalse();
        expect($result->has('name'))->toBeTrue();
        expect($result->has('address'))->toBeTrue();
    });

    test('returns new instance without modifying original', function () {
        $result = $this->dataMap->except('age');

        expect($result)->not->toBe($this->dataMap);
        expect($this->dataMap->has('age'))->toBeTrue(); // Original unchanged
        expect($result->has('age'))->toBeFalse();
    });

    test('handles non-existing keys gracefully', function () {
        $result = $this->dataMap->except('nonexistent', 'name');

        expect($result->has('name'))->toBeFalse();
        expect($result->has('age'))->toBeTrue();
    });

    test('works with no arguments', function () {
        $result = $this->dataMap->except();

        expect($result->toArray())->toBe($this->testData);
    });
});

describe('only method', function () {
    test('keeps only specified keys', function () {
        $result = $this->dataMap->only('name', 'age');

        expect($result->has('name'))->toBeTrue();
        expect($result->has('age'))->toBeTrue();
        expect($result->has('address'))->toBeFalse();
        expect($result->has('hobbies'))->toBeFalse();
    });

    test('returns new instance without modifying original', function () {
        $result = $this->dataMap->only('name');

        expect($result)->not->toBe($this->dataMap);
        expect($this->dataMap->has('age'))->toBeTrue(); // Original unchanged
        expect($result->has('age'))->toBeFalse();
    });

    test('handles non-existing keys gracefully', function () {
        $result = $this->dataMap->only('name', 'nonexistent');

        expect($result->has('name'))->toBeTrue();
        expect($result->has('nonexistent'))->toBeFalse();
        expect($result->fields())->toBe(['name']);
    });

    test('returns empty DataMap when no keys match', function () {
        $result = $this->dataMap->only('nonexistent1', 'nonexistent2');

        expect($result->toArray())->toBe([]);
    });
});

describe('with method', function () {
    test('adds new values to DataMap', function () {
        $result = $this->dataMap->with([
            'email' => 'john@example.com',
            'phone' => '555-1234'
        ]);

        expect($result->get('email'))->toBe('john@example.com');
        expect($result->get('phone'))->toBe('555-1234');
    });

    test('overwrites existing values', function () {
        $result = $this->dataMap->with([
            'name' => 'Jane Doe',
            'age' => 25
        ]);

        expect($result->get('name'))->toBe('Jane Doe');
        expect($result->get('age'))->toBe(25);
    });

    test('handles DataMap instances in values', function () {
        $nestedDataMap = new DataMap(['nested' => 'value']);
        $result = $this->dataMap->with([
            'nested_map' => $nestedDataMap
        ]);

        expect($result->get('nested_map')->toArray())->toBe(['nested' => 'value']);
    });

    test('returns new instance without modifying original', function () {
        $result = $this->dataMap->with(['new' => 'value']);

        expect($result)->not->toBe($this->dataMap);
        expect($this->dataMap->has('new'))->toBeFalse(); // Original unchanged
        expect($result->has('new'))->toBeTrue();
    });
});

describe('clone method', function () {
    test('creates a copy of DataMap', function () {
        $cloned = $this->dataMap->clone();

        expect($cloned)->not->toBe($this->dataMap);
        expect($cloned)->toBeInstanceOf(DataMap::class);
        expect($cloned->toArray())->toBe($this->testData);
    });

    test('modifications to clone do not affect original', function () {
        $cloned = $this->dataMap->clone();
        $cloned->set('name', 'Jane Doe');

        expect($this->dataMap->get('name'))->toBe('John Doe');
        expect($cloned->get('name'))->toBe('Jane Doe');
    });
});

describe('__clone magic method', function () {
    test('creates deep copy of nested DataMaps', function () {
        $nested = new DataMap(['deep' => 'value']);
        $dataMap = new DataMap(['nested' => $nested]);

        $cloned = clone $dataMap;
        $cloned->get('nested')->set('deep', 'changed');

        expect($dataMap->get('nested')->get('deep'))->toBe('value'); // Original unchanged
        expect($cloned->get('nested')->get('deep'))->toBe('changed');
    });

    test('handles arrays correctly during cloning', function () {
        $cloned = clone $this->dataMap;
        $cloned->set('hobbies', ['gaming']);

        expect($this->dataMap->get('hobbies')->toArray())->toBe(['reading', 'swimming']);
        expect($cloned->get('hobbies')->toArray())->toBe(['gaming']);
    });
});

describe('Option-based toMap refactoring edge cases', function () {
    test('toMap throws exception for non-existent path', function () {
        expect(fn() => $this->dataMap->toMap('nonexistent.path'))
            ->toThrow(InvalidArgumentException::class)
            ->toThrow('Path \'nonexistent.path\' does not exist or does not lead to an array or DataMap.');
    });

    test('toMap throws exception for scalar value path', function () {
        expect(fn() => $this->dataMap->toMap('name'))
            ->toThrow(InvalidArgumentException::class)
            ->toThrow('Path \'name\' does not exist or does not lead to an array or DataMap.');
    });

    test('toMap works with null path using Option', function () {
        $map = $this->dataMap->toMap(null);

        expect($map)->toBeInstanceOf(\Aimeos\Map::class);
        expect($map->toArray())->toBe($this->testData);
    });

    test('toMap handles empty string path', function () {
        $this->dataMap->set('', 'empty_key_value');
        $map = $this->dataMap->toMap('');

        expect($map)->toBeInstanceOf(\Aimeos\Map::class);
    });
});

describe('getType edge cases', function () {
    test('throws exception for non-existent key', function () {
        expect(fn() => $this->dataMap->getType('nonexistent'))
            ->toThrow(InvalidArgumentException::class)
            ->toThrow('Key \'nonexistent\' does not exist.');
    });

    test('returns object for DataMap instances', function () {
        $this->dataMap->set('nested', new DataMap(['test' => 'value']));

        expect($this->dataMap->getType('nested'))->toBe('object');
    });
});

describe('fromJson edge cases', function () {
    test('throws exception for invalid JSON', function () {
        expect(fn() => DataMap::fromJson('invalid json'))
            ->toThrow(InvalidArgumentException::class)
            ->toThrow('Invalid JSON provided');
    });

    test('handles empty JSON object', function () {
        $dataMap = DataMap::fromJson('{}');

        expect($dataMap)->toBeInstanceOf(DataMap::class);
        expect($dataMap->toArray())->toBe([]);
    });

    test('handles JSON with nested structures', function () {
        $json = json_encode($this->testData);
        $dataMap = DataMap::fromJson($json);

        expect($dataMap->get('address.street'))->toBe('123 Main St');
    });
});