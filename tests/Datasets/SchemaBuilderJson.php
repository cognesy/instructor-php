<?php

dataset('schema_builder_json', [[[
    '$comment' => 'Tests\Examples\SchemaBuilder\TestObject',
    'type' => 'object',
    'properties' => [
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
            '$comment' => 'Tests\Examples\SchemaBuilder\TestEnum',
            'description' => 'Enum property',
            'type' => 'string',
            'enum' => ['one', 'two', 'three'],
        ],
        'objectProperty' => [
            '$comment' => 'Tests\Examples\SchemaBuilder\TestNestedObject',
            'description' => 'Object property',
            'type' => 'object',
            'properties' => [
                'nestedStringProperty' => [
                    'type' => 'string',
                ],
                'nestedObjectProperty' => [
                    '$comment' => 'Tests\Examples\SchemaBuilder\TestDoubleNestedObject',
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
        'arrayObjectProperty' => [
            'description' => 'Array of objects property',
            'type' => 'array',
            'items' => [
                '$comment' => 'Tests\Examples\SchemaBuilder\TestNestedObject',
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
        'arrayEnumProperty' => [
            'description' => 'Array of enum property',
            'type' => 'array',
            'items' => [
                '$comment' => 'Tests\Examples\SchemaBuilder\TestEnum',
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
        'arrayObjectProperty',
        'arrayEnumProperty'
    ],
]]]);
