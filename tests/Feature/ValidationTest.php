<?php
namespace Tests;

use Cognesy\Instructor\Validators\Symfony\Validator;
use Tests\Examples\Person;


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
