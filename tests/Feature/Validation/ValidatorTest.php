<?php

use Cognesy\Instructor\Validators\Symfony\Validator;
use Symfony\Component\Validator\Constraints as Assert;

test('it validates an object and returns errors', function () {
    // Arrange
    $validator = new Validator();

    $invalidObject = new class {
        #[Assert\NotBlank]
        public ?string $name = null;

        #[Assert\GreaterThan(18)]
        public int $age = 17;
    };

    // Act
    $errors = $validator->validate($invalidObject);

    // Assert
    $expectedErrors = [
        'Error in name =  (This value should not be blank.)',
        'Error in age = 17 (This value should be greater than 18.)',
    ];
    expect($errors)->toEqual($expectedErrors);
});

test('it returns no errors for a valid object', function () {
    // Arrange
    $validator = new Validator();

    $validObject = new class {
        #[Assert\NotBlank]
        public string $name = 'John Doe';

        #[Assert\GreaterThan(18)]
        public int $age = 25;
    };

    // Act
    $errors = $validator->validate($validObject);

    // Assert
    expect($errors)->toHaveCount(0);
});