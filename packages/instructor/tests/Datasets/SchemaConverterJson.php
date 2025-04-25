<?php

dataset('schema_converter_json', [[[
    'x-php-class' => 'Cognesy\Instructor\Tests\Examples\SchemaConverter\TestObject',
    'type' => 'object',
    'properties' => [
        'optionalProperty' => [
            'description' => 'Optional property',
            'type' => 'string',
        ],
        'stringProperty' => [
            'description' => 'String property',
            'type' => 'string',
        ],
        'integerProperty' => [
            'description' => 'Integer property',
            'type' => 'integer',
        ],
        'boolProperty' => [
            'description' => 'Boolean property',
            'type' => 'boolean',
        ],
        'floatProperty' => [
            'description' => 'Float property',
            'type' => 'number',
        ],
        'enumProperty' => [
            'x-php-class' => 'Cognesy\Instructor\Tests\Examples\SchemaConverter\TestEnum',
            'description' => 'Enum property',
            'type' => 'string',
            'enum' => ['one', 'two', 'three'],
        ],
        'objectProperty' => [
            'x-php-class' => 'Cognesy\Instructor\Tests\Examples\SchemaConverter\TestNestedObject',
            'description' => 'Object property',
            'type' => 'object',
            'properties' => [
                'nestedStringProperty' => [
                    'type' => 'string',
                ],
                'nestedObjectProperty' => [
                    'x-php-class' => 'Cognesy\Instructor\Tests\Examples\SchemaConverter\TestDoubleNestedObject',
                    'type' => 'object',
                    'properties' => [
                        'nestedNestedStringProperty' => [
                            'type' => 'string',
                        ],
                    ],
                    'required' => [
                        'nestedNestedStringProperty'
                    ],
                ],
            ],
            'required' => [
                'nestedStringProperty',
                'nestedObjectProperty'
            ],
        ],
        'arrayProperty' => [
            'description' => 'Array property',
            'type' => 'array',
            'items' => [
                'type' => 'string',
            ],
        ],
        'collectionProperty' => [
            'description' => 'Collection property',
            'type' => 'array',
            'items' => [
                'x-php-class' => 'Cognesy\Instructor\Extras\Scalar\Scalar',
                'type' => 'object',
                'properties' => [
                    'nestedStringProperty' => [
                        'type' => 'string',
                    ],
                ],
            ],
        ],
        'collectionObjectProperty' => [
            'description' => 'Collection of objects property',
            'type' => 'array',
            'items' => [
                'x-php-class' => 'Cognesy\Instructor\Tests\Examples\SchemaConverter\TestNestedObject',
                'type' => 'object',
                'properties' => [
                    'nestedStringProperty' => [
                        'type' => 'string',
                    ],
                ],
                'required' => [
                    'nestedStringProperty'
                ],
            ],
        ],
        'collectionEnumProperty' => [
            'description' => 'Collection of enum property',
            'type' => 'array',
            'items' => [
                'x-php-class' => 'Cognesy\Instructor\Tests\Examples\SchemaConverter\TestEnum',
                'type' => 'string',
                'enum' => ['one', 'two', 'three'],
            ],
        ],
    ],
    'required' => [
        'stringProperty',
        'integerProperty',
        'boolProperty',
        'floatProperty',
        'enumProperty',
        'objectProperty',
        'arrayProperty',
        'collectionProperty',
        'collectionObjectProperty',
        'collectionEnumProperty'
    ],
]]]);
