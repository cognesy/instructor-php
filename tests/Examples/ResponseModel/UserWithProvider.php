<?php

namespace Tests\Examples\ResponseModel;

use Cognesy\Instructor\Contracts\CanProvideSchema;

class UserWithProvider implements CanProvideSchema {
    public function toJsonSchema() : array {
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
