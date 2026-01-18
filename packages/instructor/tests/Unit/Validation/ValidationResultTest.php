<?php declare(strict_types=1);

use Cognesy\Instructor\Validation\ValidationError;
use Cognesy\Instructor\Validation\ValidationResult;

it('stores ValidationError instances in invalid results', function () {
    $error = new ValidationError(field: 'field', value: 'value', message: 'bad');
    $result = ValidationResult::invalid($error, 'Validation failed');

    expect($result->getErrors())->toHaveCount(1);
    expect($result->getErrors()[0])->toBeInstanceOf(ValidationError::class);
});
