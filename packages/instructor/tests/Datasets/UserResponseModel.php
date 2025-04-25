<?php

dataset('user_response_model', [[[
    'x-php-class' => 'Cognesy\Instructor\Tests\Examples\ResponseModel\User',
    'type' => 'object',
    'properties' => [
        'name' => [
            'type' => 'string'
        ],
        'email' => [
            'type' => 'string'
        ],
    ],
    "required" => [
        0 => 'name',
        1 => 'email',
    ]
]]]);
