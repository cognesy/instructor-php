<?php

use Cognesy\Utils\JsonSchema\JsonSchema;

test('example from comments creates valid schema', function () {
    // Test the example from the comments at the end of JsonSchema.php
    $schema = JsonSchema::object(
        name: 'User',
        description: 'User object',
        properties: [
            JsonSchema::string(name: 'id'),
            JsonSchema::string(name: 'name'),
        ],
        requiredProperties: ['id', 'name'],
    );

    expect($schema)->toBeInstanceOf(JsonSchema::class)
        ->and($schema->type())->toBe('object')
        ->and($schema->name())->toBe('User')
        ->and($schema->description())->toBe('User object')
        ->and($schema->properties())->toHaveCount(2)
        ->and($schema->requiredProperties())->toBe(['id', 'name']);

    // Test array conversion
    $array = $schema->toArray();
    expect($array['type'])->toBe('object')
        ->and($array['description'])->toBe('User object')
        ->and($array['properties'])->toHaveCount(2)
        ->and($array['required'])->toBe(['id', 'name']);

    // Test function call conversion
    $functionCall = $schema->toFunctionCall('createUser', 'Create a new user');
    expect($functionCall['type'])->toBe('function')
        ->and($functionCall['function']['name'])->toBe('createUser')
        ->and($functionCall['function']['description'])->toBe('Create a new user')
        ->and($functionCall['function']['parameters']['type'])->toBe('object');
});

test('chained example from comments creates valid schema', function () {
    // Test the second example from the comments at the end of JsonSchema.php
    $schema = JsonSchema::array('list')
        ->withItemSchema(JsonSchema::string())
        ->withRequiredProperties(['id', 'name']);

    expect($schema)->toBeInstanceOf(JsonSchema::class)
        ->and($schema->type())->toBe('array')
        ->and($schema->name())->toBe('list')
        ->and($schema->itemSchema())->not->toBeEmpty()
        ->and($schema->requiredProperties())->toBe(['id', 'name']);

    // Test array conversion
    $array = $schema->toArray();
    expect($array['type'])->toBe('array')
        ->and($array['items'])->not->toBeEmpty();
});

test('complete user registration schema example', function () {
    // Create a complete schema for user registration
    $addressSchema = JsonSchema::object(
        name: 'Address',
        properties: [
            JsonSchema::string(name: 'street'),
            JsonSchema::string(name: 'city'),
            JsonSchema::string(name: 'state'),
            JsonSchema::string(name: 'zipCode'),
            JsonSchema::string(name: 'country'),
        ],
        requiredProperties: ['street', 'city', 'country'],
    );

    $phoneSchema = JsonSchema::object(
        name: 'Phone',
        properties: [
            JsonSchema::string(name: 'type')
                ->withEnumValues(['home', 'work', 'mobile'])
                ->withDescription('Type of phone number'),
            JsonSchema::string(name: 'number')
                ->withDescription('Phone number in international format'),
        ],
        requiredProperties: ['type', 'number'],
    );

    $userSchema = JsonSchema::object(
        name: 'UserRegistration',
        description: 'Schema for user registration',
        properties: [
            JsonSchema::string(name: 'username')
                ->withDescription('Unique username'),
            JsonSchema::string(name: 'email')
                ->withDescription('User email address')
                ->withMeta(['format' => 'email']),
            JsonSchema::string(name: 'password')
                ->withDescription('User password')
                ->withMeta(['format' => 'password', 'minLength' => 8]),
            JsonSchema::string(name: 'firstName')
                ->withDescription('User first name'),
            JsonSchema::string(name: 'lastName')
                ->withDescription('User last name'),
            JsonSchema::boolean(name: 'marketingConsent')
                ->withDescription('Consent to receive marketing emails')
                ->withNullable(false),
            $addressSchema->withName('address')
                ->withDescription('User address information')
                ->withNullable(true),
            JsonSchema::array(name: 'phoneNumbers')
                ->withDescription('User phone numbers')
                ->withItemSchema($phoneSchema)
                ->withNullable(true),
        ],
        requiredProperties: ['username', 'email', 'password', 'firstName', 'lastName', 'marketingConsent'],
        additionalProperties: false,
    );

    expect($userSchema)->toBeInstanceOf(JsonSchema::class);

    // Convert to array and check structure
    $array = $userSchema->toArray();
    expect($array['type'])->toBe('object')
        ->and($array['description'])->toBe('Schema for user registration')
        ->and($array['properties'])->toHaveCount(8)
        ->and($array['required'])->toHaveCount(6)
        ->and($array['additionalProperties'])->toBeFalse();

    // Check address property
    $addressProperty = $array['properties']['address'];
    expect($addressProperty['type'])->toBe('object')
        ->and($addressProperty['properties'])->toHaveCount(5)
        ->and($addressProperty['required'])->toHaveCount(3)
        ->and($addressProperty['nullable'])->toBeTrue();

    // Check phoneNumbers property
    $phoneNumbersProperty = $array['properties']['phoneNumbers'];
    expect($phoneNumbersProperty['type'])->toBe('array')
        ->and($phoneNumbersProperty['items'])->not->toBeEmpty()
        ->and($phoneNumbersProperty['nullable'])->toBeTrue();

    // Check that we can convert to function call
    $functionCall = $userSchema->toFunctionCall('registerUser', 'Register a new user');
    expect($functionCall['type'])->toBe('function')
        ->and($functionCall['function']['name'])->toBe('registerUser')
        ->and($functionCall['function']['description'])->toBe('Register a new user');
});

test('product catalogue schema example', function () {
    // Create a schema for a product in a catalogue
    $priceSchema = JsonSchema::object(
        name: 'Price',
        properties: [
            JsonSchema::number(name: 'amount')
                ->withDescription('Price amount'),
            JsonSchema::string(name: 'currency')
                ->withDescription('Currency code (ISO 4217)')
                ->withEnumValues(['USD', 'EUR', 'GBP', 'JPY']),
        ],
        requiredProperties: ['amount', 'currency'],
    );

    $imageSchema = JsonSchema::object(
        name: 'Image',
        properties: [
            JsonSchema::string(name: 'url')
                ->withDescription('Image URL'),
            JsonSchema::string(name: 'alt')
                ->withDescription('Image alt text')
                ->withNullable(true),
            JsonSchema::number(name: 'width')
                ->withDescription('Image width in pixels')
                ->withNullable(true),
            JsonSchema::number(name: 'height')
                ->withDescription('Image height in pixels')
                ->withNullable(true),
        ],
        requiredProperties: ['url'],
    );

    $productSchema = JsonSchema::object(
        name: 'Product',
        description: 'Product in a catalogue',
        properties: [
            JsonSchema::string(name: 'id')
                ->withDescription('Unique product ID'),
            JsonSchema::string(name: 'sku')
                ->withDescription('Stock keeping unit')
                ->withNullable(true),
            JsonSchema::string(name: 'name')
                ->withDescription('Product name'),
            JsonSchema::string(name: 'description')
                ->withDescription('Product description')
                ->withNullable(true),
            $priceSchema->withName('price')
                ->withDescription('Product price'),
            JsonSchema::array(name: 'categories')
                ->withDescription('Product categories')
                ->withItemSchema(JsonSchema::string(name: 'category')),
            JsonSchema::array(name: 'tags')
                ->withDescription('Product tags')
                ->withItemSchema(JsonSchema::string(name: 'tag'))
                ->withNullable(true),
            JsonSchema::array(name: 'images')
                ->withDescription('Product images')
                ->withItemSchema($imageSchema)
                ->withNullable(true),
            JsonSchema::boolean(name: 'inStock')
                ->withDescription('Whether the product is in stock'),
            JsonSchema::number(name: 'rating')
                ->withDescription('Product rating')
                ->withNullable(true),
        ],
        requiredProperties: ['id', 'name', 'price', 'categories', 'inStock'],
        additionalProperties: false,
    );

    expect($productSchema)->toBeInstanceOf(JsonSchema::class);

    // Convert to array and check structure
    $array = $productSchema->toArray();
    expect($array['type'])->toBe('object')
        ->and($array['description'])->toBe('Product in a catalogue')
        ->and($array['properties'])->toHaveCount(10)
        ->and($array['required'])->toHaveCount(5);

    // Check that we can convert to function call
    $functionCall = $productSchema->toFunctionCall('addProduct', 'Add a new product to the catalogue');
    expect($functionCall['type'])->toBe('function')
        ->and($functionCall['function']['name'])->toBe('addProduct')
        ->and($functionCall['function']['description'])->toBe('Add a new product to the catalogue');
});