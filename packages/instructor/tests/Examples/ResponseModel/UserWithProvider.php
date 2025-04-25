<?php

namespace Cognesy\Instructor\Tests\Examples\ResponseModel;

use Cognesy\Instructor\Contracts\CanProvideJsonSchema;

class UserWithProvider implements CanProvideJsonSchema {
    public function toJsonSchema() : array {
        return [
            'x-php-class' => User::class,
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
