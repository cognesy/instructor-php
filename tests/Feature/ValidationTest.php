<?php
namespace Tests;

use Cognesy\Instructor\Validators\Symfony\Validator;
use Tests\Examples\Person;
use Tests\Examples\PersonWithValidationMixin;

it('validates using attribute rules', function () {
    $person = new Person();
    $person->name = 'Jason';
    $person->age = 28;
    $validator = new Validator();
    expect($validator->validate($person))->toBe(true);
});


it('finds validation error', function () {
    $person = new Person();
    $person->name = 'Jason';
    $person->age = -28;
    $validator = new Validator();
    expect($validator->validate($person))->toBe(false);
});


it('uses custom validation via ValidationMixin', function () {
    $person = new PersonWithValidationMixin();
    $person->name = 'Jason';
    // age is less than 18
    $person->age = 12;
    $validator = new Validator();
    expect($validator->validate($person))->toBe(false);
    // age is more or equal to 18
    $person->age = 19;
    $validator = new Validator();
    expect($validator->validate($person))->toBe(true);
});
