<?php

use Cognesy\Utils\Json\PartialJsonParser;

test('can parse partial JSON', function ($data) {
    $json = $data[0];
    $result = $data[1];
    $parsed = (new PartialJsonParser())->parse($json, true);
    expect($parsed)->toBe($result);
})->with([
    [
        ['', []],
        ['{"', []],
        ['{"strVar"', ['strVar' => null]],
        ['{"strVar":', ['strVar' => null]],
        ['{"strVar":"', ['strVar' => '']],
        ['{"strVar":"1', ['strVar' => 1]],
        ['{"strVar":"12', ['strVar' => 12]],
        ['{"strVar":"12X', ['strVar' => "12X"]],
        ['{"strVar":"a", "intVar', ['strVar' => 'a']],
        ['{"strVar":"a", "intVar":', ['strVar' => 'a', 'intVar' => null]],
        ['{"strVar":"a", "intVar":[1', ['strVar' => 'a', 'intVar' => [1]]],
        ['{"strVar":"a", "intVar":[1,2,3]}', ['strVar' => 'a', 'intVar' => [1,2,3]]],
    ]
]);

