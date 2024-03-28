<?php

namespace Tests\Examples\ResponseModel;

use Cognesy\Instructor\Contracts\CanProvideJsonSchema;

class UserWithProvider implements CanProvideJsonSchema {
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
