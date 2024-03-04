<?php

dataset('user_function_call', [[[
    "type" => "function",
    "function" => [
        "name" => "extract_data",
        "description" => "Extract data from provided content",
        "parameters" => [
            "type" => "object",
            "properties" => [
                "name" => [
                    "type" => "string"
                ],
                "email" => [
                    "type" => "string"
                ],
            ],
            "required" => [
                0 => 'name',
                1 => 'email',
            ]
        ]
    ]
]]]);