<?php declare(strict_types=1);

use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeClass;
use Cognesy\Instructor\Transformation\Contracts\CanTransformSelf;
use Cognesy\Instructor\Validation\Contracts\CanValidateSelf;
use Cognesy\Instructor\Validation\ValidationError;
use Cognesy\Instructor\Validation\ValidationResult;

it('exposes validation value objects', function () {
    expect(class_exists(ValidationResult::class))->toBeTrue();
    expect(class_exists(ValidationError::class))->toBeTrue();
});

it('exposes the shared contracts as interfaces', function () {
    expect(interface_exists(CanValidateSelf::class))->toBeTrue();
    expect(interface_exists(CanTransformSelf::class))->toBeTrue();
    expect(interface_exists(CanDeserializeClass::class))->toBeTrue();
});
