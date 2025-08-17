<?php declare(strict_types=1);

namespace Cognesy\Instructor\Validation\Contracts;

use Cognesy\Instructor\Validation\ValidationResult;

/**
 * Class can validate scalar values - used by validator classes
 */
interface CanValidateValue
{
    /**
     * Validate provided value
     *
     * @param mixed $dataValue
     * @return \Cognesy\Instructor\Validation\ValidationResult
     */
    public function validate(mixed $dataValue): ValidationResult;
}