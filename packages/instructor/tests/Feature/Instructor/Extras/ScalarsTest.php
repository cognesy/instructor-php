<?php

use Cognesy\Instructor\Extras\Scalar\Scalar;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\Examples\Scalars\CitizenshipGroup;
use Cognesy\Instructor\Tests\MockLLM;

it('extracts int type', function () {
    $mockLLM = MockLLM::get(['{"age":28}']);

    $text = "His name is Jason, he is 28 years old.";
    $value = (new StructuredOutput)->withHttpClient($mockLLM)->with(
        messages: [
            ['role' => 'system', 'content' => $text],
            ['role' => 'user', 'content' => 'What is Jason\'s age?'],
        ],
        responseModel: Scalar::integer('age'),
    )->get();
    expect($value)->toBeInt();
    expect($value)->toBe(28);
});

it('extracts string type', function () {
    $mockLLM = MockLLM::get(['{"firstName":"Jason"}']);

    $text = "His name is Jason, he is 28 years old.";
    $value = (new StructuredOutput)->withHttpClient($mockLLM)->with(
        messages: [
            ['role' => 'system', 'content' => $text],
            ['role' => 'user', 'content' => 'What is his name?'],
        ],
        responseModel: Scalar::string(name: 'firstName'),
    )->get();
    expect($value)->toBeString();
    expect($value)->toBe("Jason");
});

it('extracts float type', function () {
    $mockLLM = MockLLM::get(['{"recordTime":11.6}']);

    $text = "His name is Jason, he is 28 years old and his 100m sprint record is 11.6 seconds.";
    $value = (new StructuredOutput)->withHttpClient($mockLLM)->with(
        messages: [
            ['role' => 'system', 'content' => $text],
            ['role' => 'user', 'content' => 'What is Jason\'s best 100m run time?'],
        ],
        responseModel: Scalar::float(name: 'recordTime'),
    )->get();
    expect($value)->toBeFloat();
    expect($value)->toBe(11.6);
});

it('extracts bool type', function () {
    $mockLLM = MockLLM::get(['{"isAdult":true}']);

    $text = "His name is Jason, he is 28 years old.";
    $value = (new StructuredOutput)->withHttpClient($mockLLM)->with(
        messages: [
            ['role' => 'system', 'content' => $text],
            ['role' => 'user', 'content' => 'Is he adult?'],
        ],
        responseModel: Scalar::boolean(name: 'isAdult'),
    )->get();
    expect($value)->toBeBool();
    expect($value)->toBe(true);
});


it('extracts enum type', function () {
    $mockLLM = MockLLM::get(['{"citizenship":"other"}']);

    $text = "His name is Jason, he is 28 years old and he lives in Germany.";
    $value = (new StructuredOutput)->withHttpClient($mockLLM)->with(
        messages: [
            ['role' => 'system', 'content' => $text],
            ['role' => 'user', 'content' => 'What is Jason\'s citizenship?'],
        ],
        responseModel: Scalar::enum(CitizenshipGroup::class, name: 'citizenship'),
    )->get();
    expect($value)->toBeInstanceOf(CitizenshipGroup::class);
    expect($value)->toBe(CitizenshipGroup::Other);
});
