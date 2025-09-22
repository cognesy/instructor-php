<?php

use Aimeos\Map;
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
        'hobbies' => ['reading', 'swimming']
    ];
    $this->dataMap = new DataMap($this->testData);
});

test('DataMap can be instantiated', function () {
    expect($this->dataMap)->toBeInstanceOf(DataMap::class);
});

test('get method returns correct values', function () {
    expect($this->dataMap->get('name'))->toBe('John Doe');
    expect($this->dataMap->get('age'))->toBe(30);
    expect($this->dataMap->get('address.street'))->toBe('123 Main St');
    expect($this->dataMap->get('hobbies'))->toBeInstanceOf(DataMap::class);
    expect($this->dataMap->get('hobbies')->toArray())->toBe(['reading', 'swimming']);
});

test('set method updates values correctly', function () {
    $this->dataMap->set('name', 'Jane Doe');
    $this->dataMap->set('address.zip', '12345');
    $this->dataMap->set('skills', ['coding', 'design']);
    $this->dataMap->set('newValue', '654');
    $this->dataMap->set('newArray.subValue', '432');
    $this->dataMap->set('newArrayWithSubarray.subValue.subsubValue', '210');

    expect($this->dataMap->get('name'))->toBe('Jane Doe');
    expect($this->dataMap->get('address.zip'))->toBe('12345');
    expect($this->dataMap->get('skills')->toArray())->toBe(['coding', 'design']);
    expect($this->dataMap->get('newValue'))->toBe('654');
    expect($this->dataMap->get('newArray.subValue'))->toBe('432');
    expect($this->dataMap->get('newArrayWithSubarray.subValue.subsubValue'))->toBe('210');
});

test('has method checks for key existence', function () {
    expect($this->dataMap->has('name'))->toBeTrue();
    expect($this->dataMap->has('address.street'))->toBeTrue();
    expect($this->dataMap->has('nonexistent'))->toBeFalse();
});

test('getType method returns correct types', function () {
    expect($this->dataMap->getType('name'))->toBe('string');
    expect($this->dataMap->getType('age'))->toBe('integer');
    expect($this->dataMap->getType('address'))->toBe('object'); // arrays are wrapped in DataMap objects
});

test('magic getter and setter work correctly', function () {
    expect($this->dataMap->name)->toBe('John Doe');

    $this->dataMap->newField = 'New Value';
    expect($this->dataMap->newField)->toBe('New Value');
});

test('toJson method returns valid JSON', function () {
    $json = $this->dataMap->toJson();
    expect(json_decode($json, true))->toBe($this->testData);
});

test('fromJson method creates DataMap from JSON', function () {
    $json = json_encode($this->testData);
    $newDataMap = DataMap::fromJson($json);

    expect($newDataMap)->toBeInstanceOf(DataMap::class);
    expect($newDataMap->toArray())->toBe($this->testData);
});

test('toArray method returns correct array', function () {
    expect($this->dataMap->toArray())->toBe($this->testData);
});

test('fromArray method creates DataMap from array', function () {
    $newDataMap = DataMap::fromArray($this->testData);

    expect($newDataMap)->toBeInstanceOf(DataMap::class);
    expect($newDataMap->toArray())->toBe($this->testData);
});

test('fields method returns correct keys', function () {
    expect($this->dataMap->fields())->toBe(['name', 'age', 'address', 'hobbies']);
});

test('map method returns Aimeos\Map instance', function () {
    $map = $this->dataMap->toMap();
    expect($map)->toBeInstanceOf(Map::class);
});

test('map method with path returns correct subset', function () {
    $addressMap = $this->dataMap->toMap('address');
    expect($addressMap)->toBeInstanceOf(Map::class);
    expect($addressMap->toArray())->toBe($this->testData['address']);
});

test('map method with wildcard path returns array of Map instances', function () {
    $this->dataMap->set('users.user1.name', 'Alice');
    $this->dataMap->set('users.user2.name', 'Bob');

    $userMaps = $this->dataMap->toMap('users.*');
    expect($userMaps)->toBeArray();
    expect(count($userMaps))->toBe(2);
    expect($userMaps['users.user1'])->toBeInstanceOf(Map::class);
    expect($userMaps['users.user2'])->toBeInstanceOf(Map::class);
})->skip('Wildcard path not implemented yet');

test('jsonSerialize method returns array', function () {
    $serialized = $this->dataMap->jsonSerialize();
    expect($serialized)->toBe($this->testData);
});