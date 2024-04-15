<?php
namespace Tests;

use Cognesy\Instructor\ApiClient\Contracts\CanCallApi;
use Cognesy\Instructor\Extras\Scalars\Scalar;
use Cognesy\Instructor\Instructor;
use Tests\Examples\Scalars\CitizenshipGroup;

it('extracts int type', function () {
    $mockLLM = MockLLM::get(['{"age":28}']);

    $text = "His name is Jason, he is 28 years old.";
    $value = (new Instructor)->withConfig([CanCallApi::class => $mockLLM])->respond(
        messages: [
            ['role' => 'system', 'content' => $text],
            ['role' => 'user', 'content' => 'What is Jason\'s age?'],
        ],
        responseModel: Scalar::integer('age'),
    );
    expect($value)->toBeInt();
    expect($value)->toBe(28);
});

it('extracts string type', function () {
    $mockLLM = MockLLM::get(['{"firstName":"Jason"}']);

    $text = "His name is Jason, he is 28 years old.";
    $value = (new Instructor)->withConfig([CanCallApi::class => $mockLLM])->respond(
        messages: [
            ['role' => 'system', 'content' => $text],
            ['role' => 'user', 'content' => 'What is his name?'],
        ],
        responseModel: Scalar::string(name: 'firstName'),
    );
    expect($value)->toBeString();
    expect($value)->toBe("Jason");
});

it('extracts float type', function () {
    $mockLLM = MockLLM::get(['{"recordTime":11.6}']);

    $text = "His name is Jason, he is 28 years old and his 100m sprint record is 11.6 seconds.";
    $value = (new Instructor)->withConfig([CanCallApi::class => $mockLLM])->respond(
        messages: [
            ['role' => 'system', 'content' => $text],
            ['role' => 'user', 'content' => 'What is Jason\'s best 100m run time?'],
        ],
        responseModel: Scalar::float(name: 'recordTime'),
    );
    expect($value)->toBeFloat();
    expect($value)->toBe(11.6);
});

it('extracts bool type', function () {
    $mockLLM = MockLLM::get(['{"isAdult":true}']);

    $text = "His name is Jason, he is 28 years old.";
    $value = (new Instructor)->withConfig([CanCallApi::class => $mockLLM])->respond(
        messages: [
            ['role' => 'system', 'content' => $text],
            ['role' => 'user', 'content' => 'Is he adult?'],
        ],
        responseModel: Scalar::boolean(name: 'isAdult'),
    );
    expect($value)->toBeBool();
    expect($value)->toBe(true);
});


it('extracts enum type', function () {
    $mockLLM = MockLLM::get(['{"citizenshipGroup":"other"}']);

    $text = "His name is Jason, he is 28 years old and he lives in Germany.";
    $value = (new Instructor)->withConfig([CanCallApi::class => $mockLLM])->respond(
        messages: [
            ['role' => 'system', 'content' => $text],
            ['role' => 'user', 'content' => 'What is Jason\'s citizenship?'],
        ],
        responseModel: Scalar::enum(CitizenshipGroup::class, name: 'citizenshipGroup'),
    );
    expect($value)->toBeEnum();
    expect($value)->toBe(CitizenshipGroup::Other->value);
});
