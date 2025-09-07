<?php

use Cognesy\Instructor\Extras\Maybe\Maybe;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\Tests\Examples\Extraction\Person;
use Cognesy\Instructor\Tests\MockHttp;

it('supports simple properties', function () {
    $mockHttp = MockHttp::get([
        '{"hasValue":true,"value":{"name":"Jason","age":28},"error":""}',
    ]);

    $text = "His name is Jason, he is 28 years old.";
    $maybePerson = (new StructuredOutput)->withHttpClient($mockHttp)->with(
        messages: [['role' => 'user', 'content' => $text]],
        responseModel: Maybe::is(Person::class),
    )->get();
    
    expect($maybePerson)->toBeInstanceOf(Maybe::class);
    expect($maybePerson->hasValue())->toBeTrue();
    expect($maybePerson->get())->toBeInstanceOf(Person::class);
    expect($maybePerson->get()->name)->toBe('Jason');
    expect($maybePerson->get()->age)->toBe(28);
    expect($maybePerson->error())->toBe('');
});

it('handles missing information', function () {
    $mockHttp = MockHttp::get([
        '{"hasValue":false,"value":null,"error":"No person information found in the text"}',
    ]);

    $text = "This text contains no person information.";
    $maybePerson = (new StructuredOutput)->withHttpClient($mockHttp)->with(
        messages: [['role' => 'user', 'content' => $text]],
        responseModel: Maybe::is(Person::class),
    )->get();
    
    expect($maybePerson)->toBeInstanceOf(Maybe::class);
    expect($maybePerson->hasValue())->toBeFalse();
    expect($maybePerson->get())->toBeNull();
    expect($maybePerson->error())->toBe('No person information found in the text');
});
