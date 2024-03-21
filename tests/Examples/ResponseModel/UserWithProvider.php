<?php

namespace Tests\Examples\ResponseModel;

use Cognesy\Instructor\Contracts\CanProvideJsonSchema;
use Cognesy\Instructor\Schema\Factories\SchemaFactory;
use Cognesy\Instructor\Schema\Factories\TypeDetailsFactory;

class UserWithProvider implements CanProvideJsonSchema {
    public function toJsonSchema(SchemaFactory $schemaFactory, TypeDetailsFactory $typeDetailsFactory) : array {
        return [
            '$comment' => User::class,
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string'
                ],
                'email' => [
                    'type' => 'string'
                ]
            ],
            'required' => ['name', 'email']
        ];
    }
}
