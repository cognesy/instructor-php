<?php declare(strict_types=1);
namespace Cognesy\Instructor\Validation\Validators;

use Cognesy\Instructor\Validation\Contracts\CanValidateObject;
use Cognesy\Instructor\Validation\ValidationError;
use Cognesy\Instructor\Validation\ValidationResult;
use Symfony\Component\Validator\Validation;

class SymfonyValidator implements CanValidateObject
{
    #[\Override]
    public function validate(object $dataObject) : ValidationResult {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        $result = $validator->validate($dataObject);

        $errors = [];
        foreach ($result as $error) {
            $path = $error->getPropertyPath();
            $value = $error->getInvalidValue();
            $message = $error->getMessage();
            /** @phpstan-ignore-next-line */
            $errors[] = new ValidationError((string) $path, $value, (string) $message);
        }

        return ValidationResult::make($errors, 'Validation failed');
    }
}
