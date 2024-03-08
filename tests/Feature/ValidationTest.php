<?php
namespace Tests;

use Cognesy\Instructor\Validators\Symfony\Validator;
use Tests\Examples\Extraction\Person;
use Tests\Examples\Extraction\PersonWithValidationMixin;

it('validates using attribute rules', function () {
    $person = new Person();
    $person->name = 'Jason';
    $person->age = 28;
    $validator = new Validator();
    expect(count($validator->validate($person)))->toBe(0);
});


it('finds validation error', function () {
    $person = new Person();
    $person->name = 'Jason';
    $person->age = -28;
    $validator = new Validator();
    expect(count($validator->validate($person)))->toBe(1);
});


it('uses custom validation via ValidationMixin', function () {
    $person = new PersonWithValidationMixin();
    $person->name = 'Jason';
    // age is less than 18
    $person->age = 12;
    $validator = new Validator();
    expect(count($validator->validate($person)))->toBe(1);
    // age is more or equal to 18
    $person->age = 19;
    $validator = new Validator();
    expect(count($validator->validate($person)))->toBe(0);
});
