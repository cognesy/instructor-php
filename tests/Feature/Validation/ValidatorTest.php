<?php

use Cognesy\Instructor\Validation\Validators\SymfonyValidator;
use Symfony\Component\Validator\Constraints as Assert;

test('it validates an object and returns errors', function () {
    $invalidObject = new class {
        #[Assert\NotBlank]
        public ?string $name = null;
        #[Assert\GreaterThan(18)]
        public int $age = 17;
    };
    $validator = new SymfonyValidator();
    $result = $validator->validate($invalidObject);
    expect($result->isInvalid())->toEqual(true);
    expect(count($result->getErrors()))->toEqual(2);
});

test('it returns no errors for a valid object', function () {
    $validObject = new class {
        #[Assert\NotBlank]
        public string $name = 'John Doe';
        #[Assert\GreaterThan(18)]
        public int $age = 25;
    };
    $validator = new SymfonyValidator();
    $result = $validator->validate($validObject);
    expect($result->isValid())->toBe(true);
});