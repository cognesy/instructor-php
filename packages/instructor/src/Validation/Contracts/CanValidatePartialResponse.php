<?php declare(strict_types=1);

namespace Cognesy\Instructor\Validation\Contracts;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Utils\Result\Result;

interface CanValidatePartialResponse
{
    /** @param array<string, mixed> $data */
    public function validatePartialResponse(array $data, ResponseModel $responseModel): Result;
}