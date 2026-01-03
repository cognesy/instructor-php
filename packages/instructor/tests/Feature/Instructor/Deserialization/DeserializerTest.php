<?php

use Cognesy\Instructor\Deserialization\Deserializers\SymfonyDeserializer;
use Cognesy\Instructor\Deserialization\Exceptions\DeserializationException;
use Cognesy\Instructor\Tests\Examples\Deserialization\Person;
use Cognesy\Instructor\Tests\Examples\Deserialization\PersonWithArray;
use Cognesy\Instructor\Tests\Examples\Deserialization\PersonWithComplexPhpDoc;
use Cognesy\Instructor\Tests\Examples\Deserialization\PersonWithNestedObject;
use Cognesy\Instructor\Tests\Examples\Deserialization\PersonWithNullableProperty;
use Cognesy\Instructor\Tests\Examples\Deserialization\PersonWithPhpDocTypes;

it('deserializes simple cases', function () {
    $deserializer = new SymfonyDeserializer();
    $data = ['name' => 'Jason', 'age' => 28];
    $class = Person::class;
    $object = $deserializer->fromArray($data, $class);

    expect($object->name)->toBe("Jason");
    expect($object->age)->toBe(28);
});

it('deserializes nested objects', function () {
    $deserializer = new SymfonyDeserializer();
    $data = ['name' => 'Jason', 'age' => 28, 'address' => ['city' => 'New York', 'country' => 'US']];
    $class = PersonWithNestedObject::class;
    $object = $deserializer->fromArray($data, $class);

    expect($object->address->city)->toBe('New York');
});

it('deserializes nested arrays', function () {
    $deserializer = new SymfonyDeserializer();
    $data = ['name' => 'Jason', 'age' => 28, 'hobbies' => ['reading', 'gym']];
    $class = PersonWithArray::class;
    $object = $deserializer->fromArray($data, $class);

    expect($object->hobbies)->toBeArray();
    expect($object->hobbies)->toHaveCount(2);
    expect($object->hobbies)->toContain('reading');
    expect($object->hobbies)->toContain('gym');
});

it('deserializes nullable values', function () {
    $deserializer = new SymfonyDeserializer();
    $data = ['name' => 'Jason', 'age' => null];
    $class = PersonWithNullableProperty::class;

    $object = $deserializer->fromArray($data, $class);
    expect($object->age)->toBeNull();
});

////////////////////////////////////////////////////////////////////////////////////////

it('throws exception for non-nullable values', function () {
    $deserializer = new SymfonyDeserializer();
    $invalidData = ['name' => 'Jason', 'age' => null];
    $class = Person::class;
    $this->expectException(DeserializationException::class);
    $deserializer->fromArray($invalidData, $class);
});

it('throws exception on wrong data type', function () {
    $deserializer = new SymfonyDeserializer();
    $invalidData = ['name' => 'Jason', 'age' => 'twenty-eight']; // age should be an integer
    $class = Person::class;
    $this->expectException(DeserializationException::class);
    $deserializer->fromArray($invalidData, $class);
});

////////////////////////////////////////////////////////////////////////////////////////

it('deserializes PHPDoc typed properties', function () {
    $deserializer = new SymfonyDeserializer();
    $data = ['name' => 'Jason', 'age' => 28, 'hobbies' => ['reading', 'coding'], 'address' => ['city' => 'New York', 'country' => 'US']];
    $class = PersonWithPhpDocTypes::class;
    $object = $deserializer->fromArray($data, $class);
    
    expect($object->name)->toBe("Jason");
    expect($object->age)->toBe(28);
    expect($object->hobbies)->toBeArray();
    expect($object->hobbies)->toHaveCount(2);
    expect($object->hobbies)->toContain('reading');
    expect($object->hobbies)->toContain('coding');
    expect($object->address)->toBeInstanceOf(\Cognesy\Instructor\Tests\Examples\Deserialization\Address::class);
    expect($object->address->city)->toBe('New York');
});

it('deserializes complex PHPDoc annotations', function () {
    $deserializer = new SymfonyDeserializer();
    $data = ['name' => 'Jane Doe', 'age' => null, 'addresses' => [['city' => 'New York', 'country' => 'US'], ['city' => 'London', 'country' => 'UK']], 'metadata' => ['status' => 'active', 'score' => 95]];
    $class = PersonWithComplexPhpDoc::class;
    $object = $deserializer->fromArray($data, $class);
    
    expect($object->name)->toBe("Jane Doe");
    expect($object->age)->toBeNull();
    expect($object->addresses)->toBeArray();
    expect($object->addresses)->toHaveCount(2);
    expect($object->addresses[0])->toBeInstanceOf(\Cognesy\Instructor\Tests\Examples\Deserialization\Address::class);
    expect($object->addresses[0]->city)->toBe('New York');
    expect($object->addresses[1]->city)->toBe('London');
    expect($object->metadata)->toBeArray();
    expect($object->metadata['status'])->toBe('active');
    expect($object->metadata['score'])->toBe(95);
});

it('deserializes PHPDoc array types', function () {
    $deserializer = new SymfonyDeserializer();
    $data = ['name' => 'Test User', 'age' => 30, 'hobbies' => ['swimming', 'hiking', 'photography'], 'address' => ['city' => 'Paris', 'country' => 'FR']];
    $class = PersonWithPhpDocTypes::class;
    $object = $deserializer->fromArray($data, $class);
    
    // Focus on the PHPDoc string[] type
    expect($object->hobbies)->toBeArray();
    expect($object->hobbies)->toHaveCount(3);
    expect($object->hobbies)->toBe(['swimming', 'hiking', 'photography']);
    
    // Each element should be a string as specified in PHPDoc
    foreach ($object->hobbies as $hobby) {
        expect($hobby)->toBeString();
    }
});