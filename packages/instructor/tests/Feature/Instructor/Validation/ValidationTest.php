<?php

use Cognesy\Instructor\Tests\Examples\Extraction\Person;
use Cognesy\Instructor\Tests\Examples\Extraction\PersonWithValidationMixin;
use Cognesy\Instructor\Validation\Validators\SymfonyValidator;

it('validates using attribute rules', function () {
    $person = new Person();
    $person->name = 'Jason';
    $person->age = 28;
    $validator = new SymfonyValidator();
    expect($validator->validate($person)->isValid())->toBe(true);
});


it('finds validation error', function () {
    $person = new Person();
    $person->name = 'Jason';
    $person->age = -28;
    $validator = new SymfonyValidator();
    expect($validator->validate($person)->isInvalid())->toBe(true);
});


it('uses custom validation via ValidationMixin', function () {
    $person = new PersonWithValidationMixin();
    $person->name = 'Jason';
    // age is less than 18
    $person->age = 12;
    $validator = new SymfonyValidator();
    expect($validator->validate($person)->isInvalid())->toBe(true);
    // age is more or equal to 18
    $person->age = 19;
    $validator = new SymfonyValidator();
    expect($validator->validate($person)->isValid())->toBe(true);
});
