<?php
use Cognesy\Instructor\Deserialization\Exceptions\DeserializationException;
use Cognesy\Instructor\Deserialization\Deserializers\SymfonyDeserializer;
use Tests\Examples\Deserialization\Person;
use Tests\Examples\Deserialization\PersonWithArray;
use Tests\Examples\Deserialization\PersonWithNestedObject;
use Tests\Examples\Deserialization\PersonWithNullableProperty;

it('deserializes simple cases', function () {
    $deserializer = new SymfonyDeserializer();
    $json = '{"name": "Jason", "age": 28}';
    $class = Person::class;
    $object = $deserializer->fromJson($json, $class);

    expect($object->name)->toBe("Jason");
    expect($object->age)->toBe(28);
});

it('deserializes nested objects', function () {
    $deserializer = new SymfonyDeserializer();
    $json = '{"name":"Jason", "age":28, "address": {"city": "New York", "country": "US"}}';
    $class = PersonWithNestedObject::class;
    $object = $deserializer->fromJson($json, $class);

    expect($object->address->city)->toBe('New York');
});

it('deserializes nested arrays', function () {
    $deserializer = new SymfonyDeserializer();
    $json = '{"name":"Jason", "age":28, "hobbies": ["reading", "gym"]}';
    $class = PersonWithArray::class;
    $object = $deserializer->fromJson($json, $class);

    expect($object->hobbies)->toBeArray();
    expect($object->hobbies)->toHaveCount(2);
    expect($object->hobbies)->toContain('reading');
    expect($object->hobbies)->toContain('gym');
});

it('deserializes nullable values', function () {
    $deserializer = new SymfonyDeserializer();
    $json = '{"name": "Jason", "age": null}';
    $class = PersonWithNullableProperty::class;

    $object = $deserializer->fromJson($json, $class);
    expect($object->age)->toBeNull();
});

////////////////////////////////////////////////////////////////////////////////////////

it('throws exception on invalid JSON', function () {
    $deserializer = new SymfonyDeserializer();
    $invalidJsonData = '{invalid_json}';
    $class = Person::class;
    $this->expectException(DeserializationException::class);
    $deserializer->fromJson($invalidJsonData, $class);
});

it('throws exception for non-nullable values', function () {
    $deserializer = new SymfonyDeserializer();
    $invalidJsonData = '{"name": "Jason", "age": null}';
    $class = Person::class;
    $this->expectException(DeserializationException::class);
    $deserializer->fromJson($invalidJsonData, $class);
});

it('throws exception on wrong data type', function () {
    $deserializer = new SymfonyDeserializer();
    $invalidJsonData = '{"name": "Jason", "age": "28"}';
    $class = Person::class;
    $this->expectException(DeserializationException::class);
    $deserializer->fromJson($invalidJsonData, $class);
});

////////////////////////////////////////////////////////////////////////////////////////

it('deserializes PHPDoc class and property information', function () {
    $deserializer = new SymfonyDeserializer();
    $json = '{"property": "value"}';
    $class = PhpDocClass::class;
    $object = $deserializer->fromJson($json, $class);
    expect($object->property)->toBe('value');
})->skip('Write test case with property information stored in PHPDoc');
