<?php

use Cognesy\Experimental\BooleanLLMFunction;
use Cognesy\Experimental\IntegerLLMFunction;
use Cognesy\Experimental\StringLLMFunction;

it('responds with boolean', function () {
    $isAdult = fn($messages) => (new BooleanLLMFunction)->make(
        name: 'is_adult',
        description: 'True if person is adult, false if not.',
        messages: [['role' => 'user', 'content' => $messages]],
    );

    $response = $isAdult('His name is Jason, he likes to play a policeman in his kindergarten.');
    dump($response);
})->skip();

it('responds with string', function () {
    $capitalOf = fn($messages) => (new StringLLMFunction)->make(
        name: 'country_capital',
        description: 'Capital of provided country',
        messages: [['role' => 'user', 'content' => $messages]],
    );

    $response = $capitalOf('France');
    dump($response);
})->skip();

it('responds with int', function () {
    $areaOf = fn($messages) => (new IntegerLLMFunction)->make(
        name: 'country_area',
        description: 'Area of provided country in km2',
        messages: [['role' => 'user', 'content' => $messages]],
    );

    $response = $areaOf('Germany');
    dump($response);
})->skip();
