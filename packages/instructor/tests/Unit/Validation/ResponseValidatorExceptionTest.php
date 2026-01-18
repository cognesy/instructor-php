<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Validation\Contracts\CanValidateObject;
use Cognesy\Instructor\Validation\ResponseValidator;
use Cognesy\Instructor\Validation\ValidationResult;

class ExplodingValidator implements CanValidateObject
{
    public function validate(object $object): ValidationResult
    {
        throw new RuntimeException('boom');
    }
}

it('converts validator exceptions into failure results', function () {
    $validator = new ResponseValidator(
        events: new EventDispatcher(),
        validators: [new ExplodingValidator()],
        config: new StructuredOutputConfig(),
    );

    $responseModel = makeAnyResponseModel(\stdClass::class);
    $result = $validator->validate(new \stdClass(), $responseModel);

    expect($result->isFailure())->toBeTrue();
});
